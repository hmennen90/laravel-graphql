<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves a field to an Eloquent relation on the parent model. Serves
 * `@hasMany`, `@hasOne`, `@belongsTo`, `@belongsToMany` and morph relations — the
 * relation name defaults to the field name (override with `relation:`).
 */
final readonly class RelationDirective implements SchemaDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $relation = $this->stringArg($node, 'relation') ?? $context->fieldName;

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function (mixed $parent) use ($relation): mixed {
                if (! $parent instanceof Model) {
                    return null;
                }
                $value = $parent->getAttribute($relation);

                return $value instanceof Collection ? $value->all() : $value;
            },
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }

    private function stringArg(DirectiveNode $node, string $name): ?string
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name === $name && $argument->value instanceof StringValueNode) {
                return $argument->value->value;
            }
        }

        return null;
    }
}
