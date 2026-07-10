<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumType;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumValueDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectField;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/**
 * `@orderBy(columns: [String!])` — adds an `orderBy: [<Field>OrderByClause!]`
 * argument (column enum + ASC/DESC) that the read directive applies to the query.
 */
final readonly class OrderByDirective extends FilterDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $columnEnum = $this->columnEnum($context, $this->prefix($context).'OrderByColumn', $this->columns($node));

        $sortOrder = $this->register($context, 'SortOrder', static fn (): EnumType => new EnumType('SortOrder', [
            new EnumValueDefinition('ASC'),
            new EnumValueDefinition('DESC'),
        ]));

        $clause = $this->register($context, $this->prefix($context).'OrderByClause', static fn (): InputObjectType => new InputObjectType(
            $context->parentTypeName.ucfirst($context->fieldName).'OrderByClause',
            [
                new InputObjectField('column', Type::nonNull($columnEnum)),
                new InputObjectField('order', Type::nonNull($sortOrder), true, 'ASC'),
            ],
        ));

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            $field->getResolver(),
            [...array_values($field->args()), Argument::make('orderBy', Type::listOf(Type::nonNull($clause)))],
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
