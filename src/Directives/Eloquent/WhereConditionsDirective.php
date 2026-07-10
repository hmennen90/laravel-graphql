<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\CustomScalarType;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumType;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumValueDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectField;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Support\JsonType;

/**
 * `@whereConditions(columns: [String!])` — adds a `where: [<Field>WhereConditions!]`
 * argument (column enum + SQL operator + JSON value) applied (AND-combined) by the
 * read directive to the Eloquent query.
 */
final readonly class WhereConditionsDirective extends FilterDirective
{
    private const array OPERATORS = ['EQ', 'NEQ', 'GT', 'GTE', 'LT', 'LTE', 'LIKE', 'IN', 'NOT_IN'];

    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $columnEnum = $this->columnEnum($context, $this->prefix($context).'WhereColumn', $this->columns($node));

        $operator = $context->getType('SqlOperator');
        if (! $operator instanceof EnumType) {
            $operator = new EnumType('SqlOperator', array_map(
                static fn (string $op): EnumValueDefinition => new EnumValueDefinition($op),
                self::OPERATORS,
            ));
            $context->registerType($operator);
        }

        $json = $context->getType('JSON');
        if (! $json instanceof CustomScalarType) {
            $json = JsonType::make();
            $context->registerType($json);
        }

        $conditions = $this->register($context, $this->prefix($context).'WhereConditions', static fn (): InputObjectType => new InputObjectType(
            $context->parentTypeName.ucfirst($context->fieldName).'WhereConditions',
            [
                new InputObjectField('column', Type::nonNull($columnEnum)),
                new InputObjectField('operator', $operator, true, 'EQ'),
                new InputObjectField('value', $json),
            ],
        ));

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            $field->getResolver(),
            [...array_values($field->args()), Argument::make('where', Type::listOf(Type::nonNull($conditions)))],
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
