<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Error\GraphQLError;
use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationType;
use Hmennen90\GraphQL\Engine\Language\AST\SelectionSetNode;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Type\Definition\AbstractType;
use Hmennen90\GraphQL\Engine\Type\Definition\CompositeType;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\LeafType;
use Hmennen90\GraphQL\Engine\Type\Definition\ListOfType;
use Hmennen90\GraphQL\Engine\Type\Definition\NonNull;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\OutputType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Type\Introspection\Introspection;
use Throwable;

/**
 * Executes a validated operation against a schema, producing an {@see ExecutionResult}.
 * Implements the spec's ExecuteQuery/ExecuteMutation, field collection, value
 * completion, non-null error propagation and partial-data-on-error semantics.
 */
final class Executor
{
    /** @var array<int, GraphQLError> */
    private array $errors = [];

    /** @var array<string, FragmentDefinitionNode> */
    private array $fragments = [];

    /** @var array<string, mixed> */
    private array $variableValues = [];

    private OperationDefinitionNode $operation;

    private readonly DefaultFieldResolver $defaultResolver;

    private function __construct(
        private readonly Schema $schema,
        private readonly DocumentNode $document,
        private readonly mixed $rootValue,
        private readonly mixed $context,
    ) {
        $this->defaultResolver = new DefaultFieldResolver();
        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $this->fragments[$definition->name] = $definition;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $variableValues
     */
    public static function execute(
        Schema $schema,
        DocumentNode $document,
        mixed $rootValue = null,
        mixed $context = null,
        array $variableValues = [],
        ?string $operationName = null,
    ): ExecutionResult {
        $executor = new self($schema, $document, $rootValue, $context);

        return $executor->run($variableValues, $operationName);
    }

    /**
     * @param  array<string, mixed>  $variableValues
     */
    private function run(array $variableValues, ?string $operationName): ExecutionResult
    {
        $operation = $this->findOperation($operationName);
        if ($operation === null) {
            return ExecutionResult::withErrors([
                new GraphQLError($operationName !== null
                    ? sprintf('Unknown operation named "%s".', $operationName)
                    : 'Must provide an operation.'),
            ]);
        }

        $rootType = match ($operation->operation) {
            OperationType::QUERY => $this->schema->getQueryType(),
            OperationType::MUTATION => $this->schema->getMutationType(),
            OperationType::SUBSCRIPTION => $this->schema->getSubscriptionType(),
        };

        if ($rootType === null) {
            return ExecutionResult::withErrors([
                new GraphQLError(sprintf('Schema is not configured for %ss.', $operation->operation->value)),
            ]);
        }

        try {
            $this->variableValues = Values::coerceVariableValues($this->schema, $operation->variableDefinitions, $variableValues);
        } catch (CoercionError $e) {
            return ExecutionResult::withErrors([$e]);
        }

        $this->operation = $operation;
        $fields = FieldCollector::collect($this->schema, $rootType, $operation->selectionSet, $this->variableValues, $this->fragments);

        try {
            $data = $this->executeFields($rootType, $this->rootValue, [], $fields);
        } catch (Throwable $e) {
            // A non-null violation bubbled synchronously to the root.
            $this->errors[] = $e instanceof GraphQLError ? $e : new GraphQLError($e->getMessage(), previous: $e);

            return new ExecutionResult(null, $this->errors);
        }

        // Fully synchronous execution: no promises were created, so we already have data.
        if (! $data instanceof SyncPromise) {
            /** @var array<string, mixed> $data */
            return new ExecutionResult($data, $this->errors);
        }

        $this->drain();

        if ($data->state === SyncPromise::FULFILLED) {
            /** @var array<string, mixed> $resolved */
            $resolved = $data->value;

            return new ExecutionResult($resolved, $this->errors);
        }

        // A non-null violation propagated to the root: data is null.
        if ($data->value instanceof GraphQLError) {
            $this->errors[] = $data->value;
        } elseif ($data->value instanceof Throwable) {
            $this->errors[] = new GraphQLError($data->value->getMessage(), previous: $data->value);
        }

        return new ExecutionResult(null, $this->errors);
    }

    /**
     * Run the microtask queue, interleaving batched deferred fetches, until quiescent.
     */
    private function drain(): void
    {
        while (true) {
            SyncPromise::runQueue();
            if (! Deferred::runQueue()) {
                break;
            }
        }
    }

    private function findOperation(?string $operationName): ?OperationDefinitionNode
    {
        $found = null;
        foreach ($this->document->definitions as $definition) {
            if (! $definition instanceof OperationDefinitionNode) {
                continue;
            }
            if ($operationName === null) {
                if ($found !== null) {
                    return null; // ambiguous
                }
                $found = $definition;
            } elseif ($definition->name === $operationName) {
                return $definition;
            }
        }

        return $found;
    }

    /**
     * @param  array<int, string|int>  $path
     * @param  array<string, array<int, FieldNode>>  $fields
     */
    private function executeFields(ObjectType $parentType, mixed $source, array $path, array $fields): mixed
    {
        $keys = [];
        $values = [];
        $hasPromise = false;

        foreach ($fields as $responseKey => $fieldNodes) {
            $fieldName = $fieldNodes[0]->name;

            if ($fieldName === '__typename') {
                $keys[] = $responseKey;
                $values[] = $parentType->name();

                continue;
            }

            $fieldDef = $this->resolveFieldDefinition($parentType, $fieldName);
            if ($fieldDef === null) {
                continue;
            }

            $value = $this->executeField($parentType, $source, $fieldDef, $fieldNodes, [...$path, $responseKey]);
            $hasPromise = $hasPromise || $value instanceof SyncPromise;
            $keys[] = $responseKey;
            $values[] = $value;
        }

        // Fast path: no field deferred, so assemble the object synchronously.
        if (! $hasPromise) {
            $result = [];
            foreach ($keys as $index => $key) {
                $result[$key] = $values[$index];
            }

            return $result;
        }

        $promises = array_map(
            static fn (mixed $value): SyncPromise => $value instanceof SyncPromise ? $value : SyncPromise::resolved($value),
            $values,
        );

        return SyncPromise::all($promises)->then(static function (array $resolved) use ($keys): array {
            $result = [];
            foreach ($keys as $index => $key) {
                $result[$key] = $resolved[$index];
            }

            return $result;
        });
    }

    private function resolveFieldDefinition(ObjectType $parentType, string $fieldName): ?FieldDefinition
    {
        if ($parentType === $this->schema->getQueryType()) {
            if ($fieldName === '__schema') {
                return Introspection::schemaMetaFieldDef();
            }
            if ($fieldName === '__type') {
                return Introspection::typeMetaFieldDef();
            }
        }

        return $parentType->hasField($fieldName) ? $parentType->getField($fieldName) : null;
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     */
    private function executeField(
        ObjectType $parentType,
        mixed $source,
        FieldDefinition $fieldDef,
        array $fieldNodes,
        array $path,
    ): mixed {
        $returnType = $fieldDef->getType();

        try {
            $args = Values::coerceArgumentValues($fieldDef, $fieldNodes[0], $this->variableValues);
            $info = new ResolveInfo(
                $fieldNodes[0]->name,
                $fieldNodes,
                $returnType,
                $parentType,
                $path,
                $this->schema,
                $this->variableValues,
                $this->operation,
                $this->rootValue,
            );

            $resolver = $fieldDef->getResolver() ?? $this->defaultResolver;
            $resolve = fn (): mixed => $resolver($source, $args, $this->context, $info);

            foreach ($fieldNodes[0]->directives as $directiveNode) {
                $middleware = $this->schema->getDirectiveMiddleware($directiveNode->name);
                if ($middleware !== null) {
                    $next = $resolve;
                    $resolve = fn (): mixed => $middleware->handle($directiveNode, $info, $next);
                }
            }

            $resolved = $resolve();
        } catch (Throwable $e) {
            return $this->handleFieldError($e, $returnType, $fieldNodes, $path);
        }

        // Async path: a resolver returned a promise (e.g. a DataLoader deferred).
        if ($resolved instanceof SyncPromise) {
            return $resolved
                ->then(fn (mixed $value): mixed => $this->completeValue($returnType, $fieldNodes, $path, $value))
                ->then(null, fn (Throwable $e): mixed => $this->handleFieldError($e, $returnType, $fieldNodes, $path));
        }

        try {
            $completed = $this->completeValue($returnType, $fieldNodes, $path, $resolved);
        } catch (Throwable $e) {
            return $this->handleFieldError($e, $returnType, $fieldNodes, $path);
        }

        if ($completed instanceof SyncPromise) {
            return $completed->then(null, fn (Throwable $e): mixed => $this->handleFieldError($e, $returnType, $fieldNodes, $path));
        }

        return $completed;
    }

    /**
     * Records a field error and returns null, or rethrows a located error for a
     * non-null field so the violation bubbles to the nearest nullable ancestor.
     *
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     */
    private function handleFieldError(Throwable $e, Type&OutputType $returnType, array $fieldNodes, array $path): mixed
    {
        $located = $this->locatedError($e, $fieldNodes, $path);
        if ($returnType instanceof NonNull) {
            throw $located;
        }
        $this->errors[] = $located;

        return null;
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     */
    private function completeValue(Type&OutputType $type, array $fieldNodes, array $path, mixed $result): mixed
    {
        if ($result instanceof SyncPromise) {
            return $result->then(fn (mixed $value): mixed => $this->completeValue($type, $fieldNodes, $path, $value));
        }

        if ($type instanceof NonNull) {
            $inner = $type->wrappedType();
            if (! $inner instanceof OutputType) {
                throw new GraphQLError('Invalid non-null wrapper over a non-output type.', path: $path);
            }

            $completed = $this->completeValue($inner, $fieldNodes, $path, $result);
            if ($completed instanceof SyncPromise) {
                return $completed->then(fn (mixed $value): mixed => $this->assertNonNull($value, $fieldNodes, $path));
            }

            return $this->assertNonNull($completed, $fieldNodes, $path);
        }

        if ($result === null) {
            return null;
        }

        if ($type instanceof ListOfType) {
            return $this->completeListValue($type, $fieldNodes, $path, $result);
        }

        if ($type instanceof LeafType) {
            return $type->serialize($result);
        }

        if ($type instanceof CompositeType) {
            return $this->completeObjectValue($type, $fieldNodes, $path, $result);
        }

        throw new GraphQLError('Cannot complete value for an unknown type.', path: $path);
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     */
    private function assertNonNull(mixed $completed, array $fieldNodes, array $path): mixed
    {
        if ($completed === null) {
            throw new GraphQLError(
                sprintf('Cannot return null for non-nullable field "%s".', $fieldNodes[0]->name),
                nodes: $fieldNodes,
                path: $path,
            );
        }

        return $completed;
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     */
    private function completeListValue(ListOfType $type, array $fieldNodes, array $path, mixed $result): mixed
    {
        if (! is_iterable($result)) {
            throw new GraphQLError(
                sprintf('Expected field "%s" to return an iterable.', $fieldNodes[0]->name),
                nodes: $fieldNodes,
                path: $path,
            );
        }

        $inner = $type->wrappedType();
        if (! $inner instanceof OutputType) {
            throw new GraphQLError('Invalid list wrapper over a non-output type.', path: $path);
        }

        $items = [];
        $hasPromise = false;
        $index = 0;
        foreach ($result as $item) {
            $itemPath = [...$path, $index];
            try {
                $completed = $this->completeValue($inner, $fieldNodes, $itemPath, $item);
            } catch (Throwable $e) {
                // A synchronous non-null item error bubbles out to nullify the list.
                $completed = $this->handleListItemError($e, $inner, $fieldNodes, $itemPath);
            }

            if ($completed instanceof SyncPromise) {
                $hasPromise = true;
                $completed = $completed->then(null, fn (Throwable $e): mixed => $this->handleListItemError($e, $inner, $fieldNodes, $itemPath));
            }

            $items[] = $completed;
            $index++;
        }

        // Fast path: every item completed synchronously.
        if (! $hasPromise) {
            return $items;
        }

        $promises = array_map(
            static fn (mixed $value): SyncPromise => $value instanceof SyncPromise ? $value : SyncPromise::resolved($value),
            $items,
        );

        return SyncPromise::all($promises);
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $itemPath
     */
    private function handleListItemError(Throwable $e, OutputType $inner, array $fieldNodes, array $itemPath): mixed
    {
        $located = $this->locatedError($e, $fieldNodes, $itemPath);
        if ($inner instanceof NonNull) {
            throw $located;
        }
        $this->errors[] = $located;

        return null;
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     */
    private function completeObjectValue(CompositeType $type, array $fieldNodes, array $path, mixed $result): mixed
    {
        $objectType = $this->resolveObjectType($type, $result, $fieldNodes, $path);

        $merged = [];
        foreach ($fieldNodes as $fieldNode) {
            if ($fieldNode->selectionSet !== null) {
                foreach ($fieldNode->selectionSet->selections as $selection) {
                    $merged[] = $selection;
                }
            }
        }

        $subFields = FieldCollector::collect(
            $this->schema,
            $objectType,
            new SelectionSetNode($merged),
            $this->variableValues,
            $this->fragments,
        );

        return $this->executeFields($objectType, $result, $path, $subFields);
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     */
    private function resolveObjectType(CompositeType $type, mixed $result, array $fieldNodes, array $path): ObjectType
    {
        if ($type instanceof ObjectType) {
            return $type;
        }

        if ($type instanceof AbstractType) {
            $resolved = $type->resolveType($result, $this->context);
            if (is_string($resolved)) {
                $named = $this->schema->getType($resolved);
                if ($named instanceof ObjectType) {
                    return $named;
                }
            } elseif ($resolved instanceof ObjectType) {
                return $resolved;
            }

            foreach ($this->schema->getPossibleTypes($type) as $possible) {
                if ($possible->isTypeOf($result, $this->context) === true) {
                    return $possible;
                }
            }
        }

        throw new GraphQLError(
            sprintf('Unable to resolve concrete type for abstract type "%s".', $type->name()),
            nodes: $fieldNodes,
            path: $path,
        );
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     */
    private function locatedError(Throwable $error, array $fieldNodes, array $path): GraphQLError
    {
        if ($error instanceof GraphQLError && $error->getPath() !== []) {
            return $error;
        }

        $extensions = $error instanceof GraphQLError ? $error->getExtensions() : [];

        return new GraphQLError(
            $error->getMessage(),
            nodes: $fieldNodes,
            path: $path,
            previous: $error,
            extensions: $extensions,
        );
    }
}
