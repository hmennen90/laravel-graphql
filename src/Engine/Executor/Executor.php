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
        } catch (GraphQLError $e) {
            $this->errors[] = $e;
            $data = null;
        }

        return new ExecutionResult($data, $this->errors);
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
     * @return array<string, mixed>
     */
    private function executeFields(ObjectType $parentType, mixed $source, array $path, array $fields): array
    {
        $results = [];

        foreach ($fields as $responseKey => $fieldNodes) {
            $fieldName = $fieldNodes[0]->name;

            if ($fieldName === '__typename') {
                $results[$responseKey] = $parentType->name();

                continue;
            }

            $fieldDef = $this->resolveFieldDefinition($parentType, $fieldName);
            if ($fieldDef === null) {
                continue;
            }

            $results[$responseKey] = $this->executeField(
                $parentType,
                $source,
                $fieldDef,
                $fieldNodes,
                [...$path, $responseKey],
            );
        }

        return $results;
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
            $resolved = $resolver($source, $args, $this->context, $info);

            return $this->completeValue($returnType, $fieldNodes, $path, $resolved);
        } catch (Throwable $e) {
            $located = $this->locatedError($e, $fieldNodes, $path);
            if ($returnType instanceof NonNull) {
                throw $located;
            }
            $this->errors[] = $located;

            return null;
        }
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     */
    private function completeValue(Type&OutputType $type, array $fieldNodes, array $path, mixed $result): mixed
    {
        if ($type instanceof NonNull) {
            $inner = $type->wrappedType();
            if (! $inner instanceof OutputType) {
                throw new GraphQLError('Invalid non-null wrapper over a non-output type.', path: $path);
            }
            $completed = $this->completeValue($inner, $fieldNodes, $path, $result);
            if ($completed === null) {
                throw new GraphQLError(
                    sprintf('Cannot return null for non-nullable field "%s".', $fieldNodes[0]->name),
                    nodes: $fieldNodes,
                    path: $path,
                );
            }

            return $completed;
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
     * @return array<int, mixed>
     */
    private function completeListValue(ListOfType $type, array $fieldNodes, array $path, mixed $result): array
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
        $index = 0;
        foreach ($result as $item) {
            $itemPath = [...$path, $index];
            try {
                $items[] = $this->completeValue($inner, $fieldNodes, $itemPath, $item);
            } catch (Throwable $e) {
                $located = $this->locatedError($e, $fieldNodes, $itemPath);
                if ($inner instanceof NonNull) {
                    throw $located;
                }
                $this->errors[] = $located;
                $items[] = null;
            }
            $index++;
        }

        return $items;
    }

    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     * @return array<string, mixed>
     */
    private function completeObjectValue(CompositeType $type, array $fieldNodes, array $path, mixed $result): array
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
