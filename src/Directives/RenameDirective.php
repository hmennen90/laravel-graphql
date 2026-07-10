<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;

/** `@rename(attribute: "db_column")` — resolves a field from a differently-named source attribute. */
final readonly class RenameDirective implements SchemaDirective
{
    use ReadsArguments;

    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $attribute = $this->stringArg($node, 'attribute') ?? $context->fieldName;

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function (mixed $source) use ($attribute): mixed {
                if (is_array($source)) {
                    return $source[$attribute] ?? null;
                }
                if (is_object($source)) {
                    return $source->{$attribute} ?? null;
                }

                return null;
            },
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
