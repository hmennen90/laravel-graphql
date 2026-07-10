<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * `@find` — resolves a field to a single model, constrained by all provided
 * field arguments (e.g. `user(id: ID!): User @find`).
 */
readonly class FindDirective extends EloquentDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $modelClass = $this->modelClass($node, $field);

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function ($root, array $args) use ($modelClass): ?Model {
                $query = $modelClass::query();
                foreach ($args as $column => $value) {
                    $query->where($column, $value);
                }

                return $query->first();
            },
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
