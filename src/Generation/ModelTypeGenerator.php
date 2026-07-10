<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Generation;

use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\OutputType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Support\JsonType;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates a GraphQL {@see ObjectType} from an Eloquent model's metadata
 * (primary key, fillable attributes, casts and timestamps) — no schema
 * duplication, the model stays the single source of truth.
 */
final class ModelTypeGenerator
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function fromModel(string $modelClass, ?string $name = null): ObjectType
    {
        $model = new $modelClass();
        $name ??= class_basename($modelClass);
        $key = $model->getKeyName();
        $casts = $model->getCasts();

        $attributes = [$key, ...$model->getFillable(), ...array_keys($casts)];
        if ($model->usesTimestamps()) {
            $attributes[] = $model->getCreatedAtColumn();
            $attributes[] = $model->getUpdatedAtColumn();
        }

        $seen = [];
        $fields = [];
        foreach ($attributes as $attribute) {
            if (! is_string($attribute) || $attribute === '' || isset($seen[$attribute])) {
                continue;
            }
            $seen[$attribute] = true;

            $cast = $casts[$attribute] ?? 'string';
            $type = $attribute === $key
                ? Type::nonNull(Type::id())
                : $this->mapCast(is_string($cast) ? $cast : 'string');

            $fields[] = FieldDefinition::make($attribute, $type);
        }

        return new ObjectType($name, $fields);
    }

    private function mapCast(string $cast): Type&OutputType
    {
        $base = explode(':', $cast)[0];

        return match ($base) {
            'int', 'integer' => Type::int(),
            'real', 'float', 'double', 'decimal' => Type::float(),
            'bool', 'boolean' => Type::boolean(),
            'array', 'json', 'object', 'collection' => JsonType::make(),
            default => Type::string(),
        };
    }
}
