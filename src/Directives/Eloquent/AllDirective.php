<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Eloquent\Query\QueryModifiers;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;

/**
 * `@all` — resolves a field to every record of the associated Eloquent model
 * (from the `model:` argument or the field's return type name by convention).
 */
final readonly class AllDirective extends EloquentDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $modelClass = $this->modelClass($node, $field);

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static fn ($root, array $args): array => QueryModifiers::apply($modelClass::query(), $args)->get()->all(),
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
