<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL;

use Closure;
use Hmennen90\GraphQL\Contracts\ProvidesSchema;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Error\SyntaxError;
use Hmennen90\GraphQL\Engine\Executor\ExecutionResult;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationType;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Validation\DocumentValidator;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * The central service: resolves the configured schema (once) and runs
 * parse → validate → execute for incoming operations.
 */
final class GraphQL
{
    private ?Schema $schema = null;

    /**
     * @param  array<array-key, mixed>  $config
     */
    public function __construct(
        private readonly Container $container,
        private array $config,
    ) {
    }

    public function schema(): Schema
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $schema = $this->buildSchema();

        $errors = $schema->validate();
        if ($errors !== []) {
            $messages = implode('; ', array_map(static fn ($e): string => $e->getMessage(), $errors));

            throw new RuntimeException('Invalid GraphQL schema: '.$messages);
        }

        return $this->schema = $schema;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function execute(
        string $query,
        array $variables = [],
        ?string $operationName = null,
        mixed $context = null,
        mixed $rootValue = null,
    ): ExecutionResult {
        try {
            $document = Parser::parse($query);
        } catch (SyntaxError $e) {
            return ExecutionResult::withErrors([$e]);
        }

        $errors = DocumentValidator::validate($this->schema(), $document);
        if ($errors !== []) {
            return ExecutionResult::withErrors($errors);
        }

        return Executor::execute($this->schema(), $document, $rootValue, $context, $variables, $operationName);
    }

    /**
     * Analyze an operation's type and root field without executing it — used to
     * route subscription operations to the subscription manager.
     */
    public function analyze(string $query, ?string $operationName = null): OperationAnalysis
    {
        try {
            $document = Parser::parse($query);
        } catch (SyntaxError) {
            return new OperationAnalysis(false, null);
        }

        foreach ($document->definitions as $definition) {
            if (! $definition instanceof OperationDefinitionNode) {
                continue;
            }
            if ($operationName !== null && $definition->name !== $operationName) {
                continue;
            }

            $isSubscription = $definition->operation === OperationType::SUBSCRIPTION;
            $rootField = null;
            foreach ($definition->selectionSet->selections as $selection) {
                if ($selection instanceof FieldNode) {
                    $rootField = $selection->name;
                    break;
                }
            }

            return new OperationAnalysis($isSubscription, $rootField);
        }

        return new OperationAnalysis(false, null);
    }

    private function buildSchema(): Schema
    {
        /** @var array<string, mixed> $schemaConfig */
        $schemaConfig = is_array($this->config['schema'] ?? null) ? $this->config['schema'] : [];

        $factory = $schemaConfig['factory'] ?? null;
        if ($factory instanceof Schema) {
            return $factory;
        }
        if ($factory instanceof Closure) {
            $built = $factory();
            if ($built instanceof Schema) {
                return $built;
            }
        }
        if (is_string($factory) && $factory !== '') {
            $instance = $this->container->make($factory);
            if ($instance instanceof ProvidesSchema) {
                return $instance->schema();
            }
            if ($instance instanceof Schema) {
                return $instance;
            }
        }

        $sdl = $this->readSdl($schemaConfig['sdl_path'] ?? []);
        if ($sdl !== '') {
            /** @var array<string, array<string, callable>> $resolvers */
            $resolvers = is_array($schemaConfig['resolvers'] ?? null) ? $schemaConfig['resolvers'] : [];

            return SchemaBuilder::fromSdl($sdl, $resolvers);
        }

        throw new RuntimeException('No GraphQL schema is configured. Set graphql.schema.factory or graphql.schema.sdl_path.');
    }

    private function readSdl(mixed $paths): string
    {
        if (is_string($paths)) {
            $paths = [$paths];
        }
        if (! is_array($paths)) {
            return '';
        }

        $parts = [];
        foreach ($paths as $path) {
            if (is_string($path) && is_file($path)) {
                $contents = file_get_contents($path);
                if ($contents !== false) {
                    $parts[] = $contents;
                }
            }
        }

        return implode("\n", $parts);
    }
}
