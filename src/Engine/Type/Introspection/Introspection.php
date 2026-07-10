<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Introspection;

use Hmennen90\GraphQL\Engine\Executor\ResolveInfo;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\Directive;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumType;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumValueDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectField;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\ListOfType;
use Hmennen90\GraphQL\Engine\Type\Definition\NonNull;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\ScalarType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Type\Definition\UnionType;

/**
 * The introspection type graph (`__Schema`, `__Type`, …) and the meta-field
 * definitions (`__schema`, `__type`) that expose a schema to tooling.
 */
final class Introspection
{
    private static ?ObjectType $schemaType = null;

    private static ?ObjectType $typeType = null;

    private static ?ObjectType $fieldType = null;

    private static ?ObjectType $inputValueType = null;

    private static ?ObjectType $enumValueType = null;

    private static ?ObjectType $directiveType = null;

    private static ?EnumType $typeKind = null;

    private static ?FieldDefinition $schemaMetaField = null;

    private static ?FieldDefinition $typeMetaField = null;

    public static function schemaMetaFieldDef(): FieldDefinition
    {
        return self::$schemaMetaField ??= FieldDefinition::make(
            '__schema',
            Type::nonNull(self::schemaType()),
            resolve: static fn (mixed $source, array $args, mixed $context, ResolveInfo $info): Schema => $info->schema,
        );
    }

    public static function typeMetaFieldDef(): FieldDefinition
    {
        return self::$typeMetaField ??= FieldDefinition::make(
            '__type',
            self::typeType(),
            resolve: static function (mixed $source, array $args, mixed $context, ResolveInfo $info): ?Type {
                $name = $args['name'] ?? null;

                return is_string($name) ? $info->schema->getType($name) : null;
            },
            args: [Argument::make('name', Type::nonNull(Type::string()))],
        );
    }

    public static function schemaType(): ObjectType
    {
        return self::$schemaType ??= new ObjectType('__Schema', static fn (): array => [
            FieldDefinition::make('description', Type::string(),
                resolve: static fn (mixed $s): ?string => $s instanceof Schema ? null : null),
            FieldDefinition::make('types', Type::nonNull(Type::listOf(Type::nonNull(self::typeType()))),
                resolve: static fn (mixed $s): array => $s instanceof Schema ? array_values($s->getTypeMap()) : []),
            FieldDefinition::make('queryType', Type::nonNull(self::typeType()),
                resolve: static fn (mixed $s): ?Type => $s instanceof Schema ? $s->getQueryType() : null),
            FieldDefinition::make('mutationType', self::typeType(),
                resolve: static fn (mixed $s): ?Type => $s instanceof Schema ? $s->getMutationType() : null),
            FieldDefinition::make('subscriptionType', self::typeType(),
                resolve: static fn (mixed $s): ?Type => $s instanceof Schema ? $s->getSubscriptionType() : null),
            FieldDefinition::make('directives', Type::nonNull(Type::listOf(Type::nonNull(self::directiveType()))),
                resolve: static fn (mixed $s): array => $s instanceof Schema ? array_values($s->getDirectives()) : []),
        ]);
    }

    public static function typeType(): ObjectType
    {
        return self::$typeType ??= new ObjectType('__Type', static fn (): array => [
            FieldDefinition::make('kind', Type::nonNull(self::typeKind()),
                resolve: static fn (mixed $s): ?string => $s instanceof Type ? self::kindOf($s) : null),
            FieldDefinition::make('name', Type::string(),
                resolve: static fn (mixed $s): ?string => self::nameOf($s)),
            FieldDefinition::make('description', Type::string(),
                resolve: static fn (mixed $s): ?string => self::descriptionOf($s)),
            FieldDefinition::make('fields', Type::listOf(Type::nonNull(self::fieldType())),
                args: [Argument::withDefault('includeDeprecated', Type::boolean(), false)],
                resolve: static fn (mixed $s, array $args): ?array => self::fieldsOf($s, (bool) ($args['includeDeprecated'] ?? false))),
            FieldDefinition::make('interfaces', Type::listOf(Type::nonNull(self::typeType())),
                resolve: static fn (mixed $s): ?array => ($s instanceof ObjectType || $s instanceof InterfaceType) ? $s->interfaces() : null),
            FieldDefinition::make('possibleTypes', Type::listOf(Type::nonNull(self::typeType())),
                resolve: static fn (mixed $s, array $a, mixed $c, ResolveInfo $info): ?array => ($s instanceof InterfaceType || $s instanceof UnionType) ? $info->schema->getPossibleTypes($s) : null),
            FieldDefinition::make('enumValues', Type::listOf(Type::nonNull(self::enumValueType())),
                args: [Argument::withDefault('includeDeprecated', Type::boolean(), false)],
                resolve: static fn (mixed $s, array $args): ?array => self::enumValuesOf($s, (bool) ($args['includeDeprecated'] ?? false))),
            FieldDefinition::make('inputFields', Type::listOf(Type::nonNull(self::inputValueType())),
                resolve: static fn (mixed $s): ?array => $s instanceof InputObjectType ? array_values($s->fields()) : null),
            FieldDefinition::make('ofType', self::typeType(),
                resolve: static fn (mixed $s): ?Type => $s instanceof NonNull || $s instanceof ListOfType ? $s->wrappedType() : null),
        ]);
    }

    public static function fieldType(): ObjectType
    {
        return self::$fieldType ??= new ObjectType('__Field', static fn (): array => [
            FieldDefinition::make('name', Type::nonNull(Type::string()),
                resolve: static fn (mixed $s): ?string => $s instanceof FieldDefinition ? $s->getName() : null),
            FieldDefinition::make('description', Type::string(),
                resolve: static fn (mixed $s): ?string => $s instanceof FieldDefinition ? $s->description() : null),
            FieldDefinition::make('args', Type::nonNull(Type::listOf(Type::nonNull(self::inputValueType()))),
                resolve: static fn (mixed $s): array => $s instanceof FieldDefinition ? array_values($s->args()) : []),
            FieldDefinition::make('type', Type::nonNull(self::typeType()),
                resolve: static fn (mixed $s): ?Type => $s instanceof FieldDefinition ? $s->getType() : null),
            FieldDefinition::make('isDeprecated', Type::nonNull(Type::boolean()),
                resolve: static fn (mixed $s): bool => $s instanceof FieldDefinition && $s->isDeprecated()),
            FieldDefinition::make('deprecationReason', Type::string(),
                resolve: static fn (mixed $s): ?string => $s instanceof FieldDefinition ? $s->deprecationReason() : null),
        ]);
    }

    public static function inputValueType(): ObjectType
    {
        return self::$inputValueType ??= new ObjectType('__InputValue', static fn (): array => [
            FieldDefinition::make('name', Type::nonNull(Type::string()),
                resolve: static fn (mixed $s): ?string => self::inputName($s)),
            FieldDefinition::make('description', Type::string(),
                resolve: static fn (mixed $s): ?string => self::inputDescription($s)),
            FieldDefinition::make('type', Type::nonNull(self::typeType()),
                resolve: static fn (mixed $s): ?Type => self::inputType($s)),
            FieldDefinition::make('defaultValue', Type::string(),
                resolve: static fn (mixed $s): ?string => self::inputDefault($s)),
        ]);
    }

    public static function enumValueType(): ObjectType
    {
        return self::$enumValueType ??= new ObjectType('__EnumValue', static fn (): array => [
            FieldDefinition::make('name', Type::nonNull(Type::string()),
                resolve: static fn (mixed $s): ?string => $s instanceof EnumValueDefinition ? $s->getName() : null),
            FieldDefinition::make('description', Type::string(),
                resolve: static fn (mixed $s): ?string => $s instanceof EnumValueDefinition ? $s->description() : null),
            FieldDefinition::make('isDeprecated', Type::nonNull(Type::boolean()),
                resolve: static fn (mixed $s): bool => $s instanceof EnumValueDefinition && $s->isDeprecated()),
            FieldDefinition::make('deprecationReason', Type::string(),
                resolve: static fn (mixed $s): ?string => $s instanceof EnumValueDefinition ? $s->deprecationReason() : null),
        ]);
    }

    public static function directiveType(): ObjectType
    {
        return self::$directiveType ??= new ObjectType('__Directive', static fn (): array => [
            FieldDefinition::make('name', Type::nonNull(Type::string()),
                resolve: static fn (mixed $s): ?string => $s instanceof Directive ? $s->name() : null),
            FieldDefinition::make('description', Type::string(),
                resolve: static fn (mixed $s): ?string => $s instanceof Directive ? $s->description() : null),
            FieldDefinition::make('locations', Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                resolve: static fn (mixed $s): array => $s instanceof Directive ? $s->locations() : []),
            FieldDefinition::make('args', Type::nonNull(Type::listOf(Type::nonNull(self::inputValueType()))),
                resolve: static fn (mixed $s): array => $s instanceof Directive ? array_values($s->args()) : []),
            FieldDefinition::make('isRepeatable', Type::nonNull(Type::boolean()),
                resolve: static fn (mixed $s): bool => $s instanceof Directive && $s->isRepeatable()),
        ]);
    }

    public static function typeKind(): EnumType
    {
        return self::$typeKind ??= new EnumType('__TypeKind', array_map(
            static fn (string $name): EnumValueDefinition => new EnumValueDefinition($name),
            ['SCALAR', 'OBJECT', 'INTERFACE', 'UNION', 'ENUM', 'INPUT_OBJECT', 'LIST', 'NON_NULL'],
        ));
    }

    private static function kindOf(Type $type): string
    {
        return match (true) {
            $type instanceof NonNull => 'NON_NULL',
            $type instanceof ListOfType => 'LIST',
            $type instanceof ScalarType => 'SCALAR',
            $type instanceof ObjectType => 'OBJECT',
            $type instanceof InterfaceType => 'INTERFACE',
            $type instanceof UnionType => 'UNION',
            $type instanceof EnumType => 'ENUM',
            $type instanceof InputObjectType => 'INPUT_OBJECT',
            default => 'SCALAR',
        };
    }

    private static function nameOf(mixed $type): ?string
    {
        if ($type instanceof ScalarType || $type instanceof ObjectType || $type instanceof InterfaceType
            || $type instanceof UnionType || $type instanceof EnumType || $type instanceof InputObjectType) {
            return $type->name();
        }

        return null;
    }

    private static function descriptionOf(mixed $type): ?string
    {
        if ($type instanceof ScalarType || $type instanceof ObjectType || $type instanceof InterfaceType
            || $type instanceof UnionType || $type instanceof EnumType || $type instanceof InputObjectType) {
            return $type->description();
        }

        return null;
    }

    /**
     * @return array<int, FieldDefinition>|null
     */
    private static function fieldsOf(mixed $type, bool $includeDeprecated): ?array
    {
        if (! ($type instanceof ObjectType || $type instanceof InterfaceType)) {
            return null;
        }

        $fields = [];
        foreach ($type->fields() as $field) {
            if (! $includeDeprecated && $field->isDeprecated()) {
                continue;
            }
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @return array<int, EnumValueDefinition>|null
     */
    private static function enumValuesOf(mixed $type, bool $includeDeprecated): ?array
    {
        if (! $type instanceof EnumType) {
            return null;
        }

        $values = [];
        foreach ($type->values() as $value) {
            if (! $includeDeprecated && $value->isDeprecated()) {
                continue;
            }
            $values[] = $value;
        }

        return $values;
    }

    private static function inputName(mixed $source): ?string
    {
        return $source instanceof Argument || $source instanceof InputObjectField ? $source->getName() : null;
    }

    private static function inputDescription(mixed $source): ?string
    {
        return $source instanceof Argument || $source instanceof InputObjectField ? $source->description() : null;
    }

    private static function inputType(mixed $source): ?Type
    {
        return $source instanceof Argument || $source instanceof InputObjectField ? $source->getType() : null;
    }

    private static function inputDefault(mixed $source): ?string
    {
        if (($source instanceof Argument || $source instanceof InputObjectField) && $source->hasDefaultValue()) {
            $encoded = json_encode($source->getDefaultValue());

            return $encoded === false ? null : $encoded;
        }

        return null;
    }
}
