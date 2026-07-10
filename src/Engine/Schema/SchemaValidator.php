<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Schema;

use Hmennen90\GraphQL\Engine\Error\GraphQLError;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumType;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\ListOfType;
use Hmennen90\GraphQL\Engine\Type\Definition\NonNull;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\ScalarType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Type\Definition\UnionType;

/**
 * Validates that a schema is well-formed (root types, valid names, non-empty
 * composites, complete interface implementations, valid union members).
 */
final class SchemaValidator
{
    private const array BUILT_IN_SCALARS = ['Int', 'Float', 'String', 'Boolean', 'ID'];

    /**
     * @return array<int, GraphQLError>
     */
    public function validate(Schema $schema): array
    {
        $errors = [];

        if ($schema->getQueryType() === null) {
            $errors[] = new GraphQLError('Query root type must be provided.');
        }

        foreach ($schema->getTypeMap() as $name => $type) {
            if ($type instanceof ScalarType && in_array($name, self::BUILT_IN_SCALARS, true)) {
                continue;
            }

            if (! $this->isValidName($name)) {
                $errors[] = new GraphQLError(sprintf('Type name "%s" is not a valid GraphQL name.', $name));
            }

            if ($type instanceof ObjectType || $type instanceof InterfaceType) {
                $this->validateFieldedType($type, $errors);
            } elseif ($type instanceof UnionType) {
                if ($type->types() === []) {
                    $errors[] = new GraphQLError(sprintf('Union type "%s" must define one or more member types.', $name));
                }
            } elseif ($type instanceof InputObjectType) {
                if ($type->fields() === []) {
                    $errors[] = new GraphQLError(sprintf('Input type "%s" must define one or more fields.', $name));
                }
            } elseif ($type instanceof EnumType) {
                if ($type->values() === []) {
                    $errors[] = new GraphQLError(sprintf('Enum type "%s" must define one or more values.', $name));
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, GraphQLError>  $errors
     */
    private function validateFieldedType(ObjectType|InterfaceType $type, array &$errors): void
    {
        if ($type->fields() === []) {
            $errors[] = new GraphQLError(sprintf('Type "%s" must define one or more fields.', $type->name()));
        }

        foreach ($type->fields() as $field) {
            if (! $this->isValidName($field->getName())) {
                $errors[] = new GraphQLError(sprintf('Field "%s.%s" is not a valid GraphQL name.', $type->name(), $field->getName()));
            }
        }

        foreach ($type->interfaces() as $interface) {
            $this->validateImplements($type, $interface, $errors);
        }
    }

    /**
     * @param  array<int, GraphQLError>  $errors
     */
    private function validateImplements(ObjectType|InterfaceType $type, InterfaceType $interface, array &$errors): void
    {
        foreach ($interface->fields() as $fieldName => $interfaceField) {
            if (! $type->hasField($fieldName)) {
                $errors[] = new GraphQLError(sprintf(
                    'Type "%s" does not implement field "%s" from interface "%s".',
                    $type->name(),
                    $fieldName,
                    $interface->name(),
                ));

                continue;
            }

            $ownType = $type->getField($fieldName)->getType();
            if (! $this->isSubtypeOf($ownType, $interfaceField->getType())) {
                $errors[] = new GraphQLError(sprintf(
                    'Field "%s.%s" of type "%s" is not compatible with interface "%s" field type "%s".',
                    $type->name(),
                    $fieldName,
                    (string) $ownType,
                    $interface->name(),
                    (string) $interfaceField->getType(),
                ));
            }
        }
    }

    private function isSubtypeOf(Type $maybeSubtype, Type $superType): bool
    {
        if ($maybeSubtype->toString() === $superType->toString()) {
            return true;
        }

        if ($superType instanceof NonNull) {
            return $maybeSubtype instanceof NonNull
                && $this->isSubtypeOf($maybeSubtype->wrappedType(), $superType->wrappedType());
        }

        if ($maybeSubtype instanceof NonNull) {
            return $this->isSubtypeOf($maybeSubtype->wrappedType(), $superType);
        }

        if ($maybeSubtype instanceof ListOfType && $superType instanceof ListOfType) {
            return $this->isSubtypeOf($maybeSubtype->wrappedType(), $superType->wrappedType());
        }

        return false;
    }

    private function isValidName(string $name): bool
    {
        return preg_match('/^[_A-Za-z][_0-9A-Za-z]*$/', $name) === 1;
    }
}
