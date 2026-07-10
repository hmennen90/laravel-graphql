<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Eloquent\ModelResolver;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/**
 * `@all` — resolves a field to every model of the associated Eloquent model.
 * The model is taken from the directive's `model:` argument or, by convention,
 * from the field's (list) return type name.
 */
final readonly class AllDirective implements SchemaDirective
{
    public function __construct(private ModelResolver $models)
    {
    }

    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $modelClass = $this->models->resolve($this->modelHint($node) ?? Type::getNamedType($field->getType())->name());

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static fn (): array => $modelClass::query()->get()->all(),
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }

    private function modelHint(DirectiveNode $node): ?string
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name === 'model' && $argument->value instanceof StringValueNode) {
                return $argument->value->value;
            }
        }

        return null;
    }
}
