<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\NamedTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\NonNullTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\NullValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\TypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\VariableDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\VariableNode;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\InputType;
use Hmennen90\GraphQL\Engine\Type\Definition\LeafType;
use Hmennen90\GraphQL\Engine\Type\Definition\ListOfType;
use Hmennen90\GraphQL\Engine\Type\Definition\NonNull;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/** Variable and argument value coercion (spec algorithms CoerceVariableValues / CoerceArgumentValues). */
final class Values
{
    /**
     * @param  array<int, VariableDefinitionNode>  $variableDefinitions
     * @param  array<string, mixed>  $rawInput
     * @return array<string, mixed>
     */
    public static function coerceVariableValues(Schema $schema, array $variableDefinitions, array $rawInput): array
    {
        $coerced = [];

        foreach ($variableDefinitions as $definition) {
            $name = $definition->variable->name;
            $type = self::typeFromAst($schema, $definition->type);
            if (! $type instanceof InputType) {
                throw new CoercionError(sprintf('Variable "$%s" is not an input type.', $name));
            }

            if (! array_key_exists($name, $rawInput)) {
                if ($definition->defaultValue !== null) {
                    $coerced[$name] = self::coerceLiteral($type, $definition->defaultValue, []);
                } elseif ($type instanceof NonNull) {
                    throw new CoercionError(sprintf('Variable "$%s" of required type "%s" was not provided.', $name, (string) $type));
                }

                continue;
            }

            $coerced[$name] = self::coerceInputValue($type, $rawInput[$name], $name);
        }

        return $coerced;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public static function coerceArgumentValues(FieldDefinition $fieldDef, FieldNode $node, array $variables): array
    {
        $coerced = [];

        $argNodes = [];
        foreach ($node->arguments as $argument) {
            $argNodes[$argument->name] = $argument->value;
        }

        foreach ($fieldDef->args() as $name => $argDef) {
            $type = $argDef->getType();

            if (! array_key_exists($name, $argNodes)) {
                if ($argDef->hasDefaultValue()) {
                    $coerced[$name] = $argDef->getDefaultValue();
                } elseif ($type instanceof NonNull) {
                    throw new CoercionError(sprintf('Argument "%s" of required type "%s" was not provided.', $name, (string) $type));
                }

                continue;
            }

            $valueNode = $argNodes[$name];
            if ($valueNode instanceof VariableNode) {
                $varName = $valueNode->name;
                if (array_key_exists($varName, $variables)) {
                    $coerced[$name] = $variables[$varName];
                } elseif ($argDef->hasDefaultValue()) {
                    $coerced[$name] = $argDef->getDefaultValue();
                } elseif ($type instanceof NonNull) {
                    throw new CoercionError(sprintf('Argument "%s" of required type "%s" was not provided.', $name, (string) $type));
                }

                continue;
            }

            $coerced[$name] = self::coerceLiteral($type, $valueNode, $variables);
        }

        return $coerced;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private static function coerceLiteral(Type&InputType $type, ValueNode $node, array $variables): mixed
    {
        if ($node instanceof VariableNode) {
            return $variables[$node->name] ?? null;
        }

        if ($type instanceof NonNull) {
            $inner = $type->wrappedType();
            if (! $inner instanceof InputType) {
                throw new CoercionError('Invalid non-null wrapper over a non-input type.');
            }

            return self::coerceLiteral($inner, $node, $variables);
        }

        if ($node instanceof NullValueNode) {
            return null;
        }

        if ($type instanceof ListOfType) {
            $inner = $type->wrappedType();
            if (! $inner instanceof InputType) {
                throw new CoercionError('Invalid list wrapper over a non-input type.');
            }
            if ($node instanceof ListValueNode) {
                return array_map(fn (ValueNode $item): mixed => self::coerceLiteral($inner, $item, $variables), $node->values);
            }

            return [self::coerceLiteral($inner, $node, $variables)];
        }

        if ($type instanceof InputObjectType) {
            if (! $node instanceof ObjectValueNode) {
                throw new CoercionError(sprintf('Expected object value for "%s".', $type->name()));
            }

            $fieldNodes = [];
            foreach ($node->fields as $objectField) {
                $fieldNodes[$objectField->name] = $objectField->value;
            }

            $object = [];
            foreach ($type->fields() as $fieldName => $field) {
                if (array_key_exists($fieldName, $fieldNodes)) {
                    $object[$fieldName] = self::coerceLiteral($field->getType(), $fieldNodes[$fieldName], $variables);
                } elseif ($field->hasDefaultValue()) {
                    $object[$fieldName] = $field->getDefaultValue();
                } elseif ($field->getType() instanceof NonNull) {
                    throw new CoercionError(sprintf('Field "%s" of required type "%s" was not provided.', $fieldName, (string) $field->getType()));
                }
            }

            return $object;
        }

        if ($type instanceof LeafType) {
            return $type->parseLiteral($node, $variables);
        }

        throw new CoercionError('Cannot coerce value for the given type.');
    }

    private static function coerceInputValue(Type&InputType $type, mixed $value, string $context): mixed
    {
        if ($type instanceof NonNull) {
            if ($value === null) {
                throw new CoercionError(sprintf('Expected non-null value for "%s".', $context));
            }
            $inner = $type->wrappedType();
            if (! $inner instanceof InputType) {
                throw new CoercionError('Invalid non-null wrapper over a non-input type.');
            }

            return self::coerceInputValue($inner, $value, $context);
        }

        if ($value === null) {
            return null;
        }

        if ($type instanceof ListOfType) {
            $inner = $type->wrappedType();
            if (! $inner instanceof InputType) {
                throw new CoercionError('Invalid list wrapper over a non-input type.');
            }
            if (is_array($value)) {
                return array_map(fn (mixed $item): mixed => self::coerceInputValue($inner, $item, $context), $value);
            }

            return [self::coerceInputValue($inner, $value, $context)];
        }

        if ($type instanceof InputObjectType) {
            if (! is_array($value)) {
                throw new CoercionError(sprintf('Expected object value for "%s".', $type->name()));
            }

            $object = [];
            foreach ($type->fields() as $fieldName => $field) {
                if (array_key_exists($fieldName, $value)) {
                    $object[$fieldName] = self::coerceInputValue($field->getType(), $value[$fieldName], $fieldName);
                } elseif ($field->hasDefaultValue()) {
                    $object[$fieldName] = $field->getDefaultValue();
                } elseif ($field->getType() instanceof NonNull) {
                    throw new CoercionError(sprintf('Field "%s" of required type "%s" was not provided.', $fieldName, (string) $field->getType()));
                }
            }

            return $object;
        }

        if ($type instanceof LeafType) {
            return $type->parseValue($value);
        }

        throw new CoercionError('Cannot coerce value for the given type.');
    }

    private static function typeFromAst(Schema $schema, TypeNode $node): ?Type
    {
        if ($node instanceof NonNullTypeNode) {
            $inner = self::typeFromAst($schema, $node->type);

            return $inner !== null ? Type::nonNull($inner) : null;
        }

        if ($node instanceof ListTypeNode) {
            $inner = self::typeFromAst($schema, $node->type);

            return $inner !== null ? Type::listOf($inner) : null;
        }

        if ($node instanceof NamedTypeNode) {
            $type = $schema->getType($node->name);

            return $type;
        }

        return null;
    }
}
