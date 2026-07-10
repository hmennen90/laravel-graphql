<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/** `@forceDelete` — permanently deletes a model (found by `id`), bypassing soft deletes. */
final readonly class ForceDeleteDirective extends EloquentDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $modelClass = $this->modelClass($node, $field);
        $key = $this->stringArg($node, 'key') ?? 'id';

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static fn ($root, array $args): ?Model => DB::transaction(static function () use ($modelClass, $args, $key): ?Model {
                $id = $args[$key] ?? null;
                $model = is_scalar($id) ? $modelClass::query()->find($id) : null;
                if (! $model instanceof Model) {
                    return null;
                }

                $method = in_array(SoftDeletes::class, class_uses_recursive($model), true) ? 'forceDelete' : 'delete';
                self::callDynamic($model, $method);

                return $model;
            }),
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
