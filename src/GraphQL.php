<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL;

use Closure;
use Hmennen90\GraphQL\Contracts\ProvidesSchema;
use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Error\SyntaxError;
use Hmennen90\GraphQL\Engine\Executor\ExecutionResult;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationType;
use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
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

        $errors = DocumentValidator::validate($this->schema(), $document, $this->validationOptions());
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

    /**
     * @return array<string, int>
     */
    private function validationOptions(): array
    {
        $security = is_array($this->config['security'] ?? null) ? $this->config['security'] : [];
        $options = [];
        if (isset($security['max_depth']) && is_int($security['max_depth'])) {
            $options['maxDepth'] = $security['max_depth'];
        }
        if (isset($security['max_complexity']) && is_int($security['max_complexity'])) {
            $options['maxComplexity'] = $security['max_complexity'];
        }

        return $options;
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

            $registry = $this->container->make(DirectiveRegistry::class);
            $document = $this->document($sdl, $schemaConfig['cache'] ?? null);

            return SchemaBuilder::fromDocument($document, $resolvers, schemaDirectives: $registry->all());
        }

        throw new RuntimeException('No GraphQL schema is configured. Set graphql.schema.factory or graphql.schema.sdl_path.');
    }

    /** Parse the SDL, using a cached AST when schema caching is enabled and present. */
    private function document(string $sdl, mixed $cache): DocumentNode
    {
        $path = $this->schemaCachePath($cache);
        if ($path !== null && is_file($path)) {
            $blob = file_get_contents($path);
            if ($blob !== false) {
                $document = @unserialize($blob);
                if ($document instanceof DocumentNode) {
                    return $document;
                }
            }
        }

        return Parser::parse($sdl);
    }

    private function schemaCachePath(mixed $cache): ?string
    {
        if (! is_array($cache) || ($cache['enabled'] ?? false) !== true) {
            return null;
        }

        return is_string($cache['path'] ?? null) ? $cache['path'] : null;
    }

    /**
     * Compile the SDL schema and write its parsed AST to the cache file.
     * Returns the written path, or null if caching does not apply (no SDL schema
     * or no configured path).
     */
    public function cacheSchema(): ?string
    {
        /** @var array<string, mixed> $schemaConfig */
        $schemaConfig = is_array($this->config['schema'] ?? null) ? $this->config['schema'] : [];

        $cache = $schemaConfig['cache'] ?? null;
        $path = is_array($cache) && is_string($cache['path'] ?? null) ? $cache['path'] : null;
        $sdl = $this->readSdl($schemaConfig['sdl_path'] ?? []);
        if ($sdl === '' || $path === null) {
            return null;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($path, serialize(Parser::parse($sdl)));

        return $path;
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
