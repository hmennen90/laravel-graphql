<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Eloquent\ModelResolver;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Shared base for Eloquent-backed field directives. */
abstract readonly class EloquentDirective implements SchemaDirective
{
    public function __construct(protected ModelResolver $models)
    {
    }

    /**
     * Resolve the Eloquent model for a field: explicit `model:` argument, else the
     * field's (unwrapped) return type name via convention.
     *
     * @return class-string<Model>
     */
    protected function modelClass(DirectiveNode $node, FieldDefinition $field): string
    {
        return $this->models->resolve(
            $this->stringArg($node, 'model') ?? Type::getNamedType($field->getType())->name(),
        );
    }

    /**
     * Call a method that lives on an Eloquent trait (e.g. SoftDeletes' `restore()` /
     * `withTrashed()`), which is not on the base Model type. Guarded at the call site
     * by a `class_uses_recursive` check.
     */
    protected static function callDynamic(object $target, string $method): mixed
    {
        return $target->{$method}();
    }

    /**
     * Arg-builder closures collected from a field's argument directives (`@eq`,
     * `@scope`, `@limit`, …). Stored as untyped resolver metadata by the builder.
     *
     * @return list<mixed>
     */
    protected static function argBuilders(FieldDefinition $field): array
    {
        $meta = $field->metadata('graphql.argBuilders');

        return is_array($meta) ? array_values($meta) : [];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<array-key, mixed>  $args
     * @param  list<mixed>  $builders
     * @return Builder<Model>
     */
    protected static function applyArgBuilders(Builder $query, array $args, array $builders): Builder
    {
        foreach ($builders as $builder) {
            if (! is_callable($builder)) {
                continue;
            }
            $result = $builder($query, $args);
            if ($result instanceof Builder) {
                $query = $result;
            }
        }

        return $query;
    }

    /**
     * @param  array<array-key, mixed>  $args
     * @return array<string, mixed>
     */
    protected static function stringKeys(array $args): array
    {
        $out = [];
        foreach ($args as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    protected function stringArg(DirectiveNode $node, string $name): ?string
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name === $name && $argument->value instanceof StringValueNode) {
                return $argument->value->value;
            }
        }

        return null;
    }
}
