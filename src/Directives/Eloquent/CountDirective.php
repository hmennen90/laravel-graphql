<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/** `@count(relation:)` — resolves a field to the count of an Eloquent relation. */
final readonly class CountDirective implements SchemaDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $relation = $this->stringArg($node, 'relation') ?? $context->fieldName;

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function (mixed $parent) use ($relation): ?int {
                if (! $parent instanceof Model) {
                    return null;
                }
                $query = $parent->{$relation}();

                return $query instanceof Relation ? $query->count() : null;
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
