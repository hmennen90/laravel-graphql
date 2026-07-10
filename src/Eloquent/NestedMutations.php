<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Persists a model together with nested relation operations. Argument keys that
 * match an Eloquent relation method are treated as nested mutations
 * (`create`/`connect`/`sync`/`update`/`delete`); everything else is a column.
 * The relations are derived from the model — no re-declaration.
 */
final class NestedMutations
{
    /**
     * @param  array<string, mixed>  $args
     */
    public static function save(Model $model, array $args): void
    {
        $attributes = [];
        $deferred = [];

        foreach ($args as $key => $value) {
            if (is_array($value) && method_exists($model, $key)) {
                $relation = $model->{$key}();
                if ($relation instanceof BelongsTo) {
                    self::applyBelongsTo($relation, $value);

                    continue;
                }
                if ($relation instanceof Relation) {
                    $deferred[$key] = $value;

                    continue;
                }
            }
            $attributes[$key] = $value;
        }

        $model->fill($attributes)->save();

        foreach ($deferred as $key => $operations) {
            self::applyMany($model->{$key}(), $operations);
        }
    }

    /**
     * @param  BelongsTo<Model, Model>  $relation
     * @param  array<array-key, mixed>  $operations
     */
    private static function applyBelongsTo(BelongsTo $relation, array $operations): void
    {
        if (isset($operations['connect'])) {
            $connect = $operations['connect'];
            if ($connect instanceof Model || is_int($connect) || is_string($connect)) {
                $relation->associate($connect);
            }

            return;
        }
        if (is_array($operations['create'] ?? null)) {
            $related = $relation->getRelated()->newQuery()->create(self::stringKeys($operations['create']));
            $relation->associate($related);
        }
    }

    /**
     * @param  array<array-key, mixed>  $operations
     */
    private static function applyMany(mixed $relation, array $operations): void
    {
        if ($relation instanceof HasMany || $relation instanceof HasOne) {
            if (is_array($operations['create'] ?? null)) {
                foreach ($operations['create'] as $attributes) {
                    if (is_array($attributes)) {
                        $relation->create(self::stringKeys($attributes));
                    }
                }
            }

            return;
        }

        if ($relation instanceof BelongsToMany) {
            if (is_array($operations['sync'] ?? null)) {
                $relation->sync($operations['sync']);
            }
            if (is_array($operations['connect'] ?? null)) {
                $relation->attach($operations['connect']);
            }
            if (is_array($operations['create'] ?? null)) {
                foreach ($operations['create'] as $attributes) {
                    if (is_array($attributes)) {
                        $relation->save($relation->getRelated()->newInstance(self::stringKeys($attributes)));
                    }
                }
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $args
     * @return array<string, mixed>
     */
    private static function stringKeys(array $args): array
    {
        $out = [];
        foreach ($args as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }
}
