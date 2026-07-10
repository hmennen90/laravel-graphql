<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Eloquent\ModelResolver;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
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
