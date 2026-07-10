<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/** `@restore` — restores a soft-deleted model (found by `id`, including trashed rows). */
final readonly class RestoreDirective extends EloquentDirective
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
                if (! is_scalar($id)) {
                    return null;
                }

                $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
                $query = $modelClass::query();
                if ($softDeletes) {
                    $withTrashed = self::callDynamic($query, 'withTrashed');
                    $query = $withTrashed instanceof Builder ? $withTrashed : $query;
                }

                $model = $query->find($id);
                if ($model instanceof Model && $softDeletes) {
                    self::callDynamic($model, 'restore');
                }

                return $model instanceof Model ? $model : null;
            }),
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
