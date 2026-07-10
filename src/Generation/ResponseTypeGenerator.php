<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Generation;

use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\OutputType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Support\JsonType;

/**
 * Infers a GraphQL {@see ObjectType} from a sample response array — e.g. the
 * output of a JsonResource (`(new UserResource($user))->toArray($request)`) or
 * any decoded JSON payload. Nested arrays become nested object types.
 */
final class ResponseTypeGenerator
{
    /**
     * @param  array<string, mixed>  $sample
     */
    public function fromArray(array $sample, string $name): ObjectType
    {
        $fields = [];
        foreach ($sample as $key => $value) {
            $field = (string) $key;
            $fields[] = FieldDefinition::make($field, $this->inferType($value, $name.ucfirst($field)));
        }

        return new ObjectType($name, $fields);
    }

    private function inferType(mixed $value, string $nestedName): Type&OutputType
    {
        return match (true) {
            is_bool($value) => Type::boolean(),
            is_int($value) => Type::int(),
            is_float($value) => Type::float(),
            is_string($value) => Type::string(),
            is_array($value) => $this->inferArrayType($value, $nestedName),
            default => JsonType::make(),
        };
    }

    /**
     * @param  array<mixed>  $value
     */
    private function inferArrayType(array $value, string $nestedName): Type&OutputType
    {
        if ($value === []) {
            return Type::listOf(JsonType::make());
        }

        if (array_is_list($value)) {
            return Type::listOf($this->inferType($value[0], $nestedName));
        }

        /** @var array<string, mixed> $value */
        return $this->fromArray($value, $nestedName);
    }
}
