<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Eloquent\NestedMutations;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * `@create` — creates a model from the field arguments (in a transaction),
 * applying nested relation operations where argument keys match relations.
 */
final readonly class CreateDirective extends EloquentDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $modelClass = $this->modelClass($node, $field);

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static fn ($root, array $args): Model => DB::transaction(static function () use ($modelClass, $args): Model {
                $model = new $modelClass();
                NestedMutations::save($model, self::stringKeys($args));

                return $model;
            }),
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
