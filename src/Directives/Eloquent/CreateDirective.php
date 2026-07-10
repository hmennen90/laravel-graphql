<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Illuminate\Support\Facades\DB;

/** `@create` — creates a model from the field arguments (in a transaction). */
final readonly class CreateDirective extends EloquentDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $modelClass = $this->modelClass($node, $field);

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static fn ($root, array $args): mixed => DB::transaction(
                static fn (): mixed => $modelClass::query()->create(self::stringKeys($args)),
            ),
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
