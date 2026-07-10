<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Generation;

use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectField;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\InputType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Support\JsonType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Generates a GraphQL {@see InputObjectType} from Laravel validation rules or a
 * FormRequest, mapping rule tokens to input types and `required` to non-null.
 */
final class ValidationInputGenerator
{
    /**
     * @param  class-string<FormRequest>  $requestClass
     */
    public function fromRequest(string $requestClass, ?string $name = null): InputObjectType
    {
        $request = new $requestClass();
        $name ??= class_basename($requestClass);

        $rules = method_exists($request, 'rules') ? $request->{'rules'}() : [];

        return $this->fromRules(is_array($rules) ? $rules : [], $name);
    }

    /**
     * @param  array<array-key, mixed>  $rules
     */
    public function fromRules(array $rules, string $name): InputObjectType
    {
        $fields = [];
        foreach ($rules as $field => $rule) {
            $field = (string) $field;
            if (str_contains($field, '.')) {
                continue; // nested/array rules are not mapped in v1
            }

            $tokens = $this->tokens($rule);
            $type = $this->mapTokens($tokens);
            if (in_array('required', $tokens, true)) {
                $type = Type::nonNull($type);
            }

            $fields[] = InputObjectField::make($field, $type);
        }

        return new InputObjectType($name, $fields);
    }

    /**
     * @return array<int, string>
     */
    private function tokens(mixed $rule): array
    {
        if (is_string($rule)) {
            return explode('|', $rule);
        }

        $tokens = [];
        if (is_array($rule)) {
            foreach ($rule as $token) {
                if (is_string($token)) {
                    $tokens[] = $token;
                }
            }
        }

        return $tokens;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function mapTokens(array $tokens): Type&InputType
    {
        foreach ($tokens as $token) {
            $name = explode(':', $token)[0];
            $mapped = match ($name) {
                'integer', 'int' => Type::int(),
                'numeric', 'decimal' => Type::float(),
                'boolean', 'bool' => Type::boolean(),
                'array', 'json' => JsonType::make(),
                default => null,
            };
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return Type::string();
    }
}
