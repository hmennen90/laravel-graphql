<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Schema;

use Hmennen90\GraphQL\Engine\Type\Definition\EnumType;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\ScalarType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Type\Definition\UnionType;

/** Prints a {@see Schema} back to GraphQL SDL. */
final class SchemaPrinter
{
    private const array BUILT_IN_SCALARS = ['Int', 'Float', 'String', 'Boolean', 'ID'];

    public static function print(Schema $schema, ?SchemaAnnotations $annotations = null): string
    {
        $types = $schema->getTypeMap();
        ksort($types);

        $blocks = [];
        $header = $annotations?->header() ?? '';
        if ($header !== '') {
            $blocks[] = $header;
        }

        foreach ($types as $name => $type) {
            if (str_starts_with($name, '__')) {
                continue;
            }
            if ($type instanceof ScalarType && in_array($name, self::BUILT_IN_SCALARS, true)) {
                continue;
            }
            $blocks[] = self::printType($type, $annotations);
        }

        return implode("\n\n", array_filter($blocks));
    }

    private static function printType(Type $type, ?SchemaAnnotations $annotations): string
    {
        return match (true) {
            $type instanceof ObjectType => self::printFielded('type', $type->name(), $type->interfaces(), $type, $annotations),
            $type instanceof InterfaceType => self::printFielded('interface', $type->name(), $type->interfaces(), $type, $annotations),
            $type instanceof InputObjectType => self::printInput($type),
            $type instanceof UnionType => sprintf('union %s = %s', $type->name(), implode(' | ', array_map(
                static fn (ObjectType $member): string => $member->name(),
                $type->types(),
            ))),
            $type instanceof EnumType => self::printEnum($type),
            $type instanceof ScalarType => 'scalar '.$type->name(),
            default => '',
        };
    }

    /**
     * @param  array<int, InterfaceType>  $interfaces
     */
    private static function printFielded(string $keyword, string $name, array $interfaces, ObjectType|InterfaceType $type, ?SchemaAnnotations $annotations): string
    {
        $header = $keyword.' '.$name;
        if ($interfaces !== []) {
            $header .= ' implements '.implode(' & ', array_map(
                static fn (InterfaceType $i): string => $i->name(),
                $interfaces,
            ));
        }
        $header .= $annotations?->typeAnnotations($name) ?? '';

        $lines = [];
        foreach ($type->fields() as $field) {
            $args = [];
            foreach ($field->args() as $arg) {
                $args[] = $arg->getName().': '.$arg->getType()->toString();
            }
            $argString = $args === [] ? '' : '('.implode(', ', $args).')';
            $lines[] = '  '.$field->getName().$argString.': '.$field->getType()->toString()
                .($annotations?->fieldAnnotations($name, $field->getName()) ?? '');
        }

        return $header." {\n".implode("\n", $lines)."\n}";
    }

    private static function printInput(InputObjectType $type): string
    {
        $lines = [];
        foreach ($type->fields() as $field) {
            $lines[] = '  '.$field->getName().': '.$field->getType()->toString();
        }

        return 'input '.$type->name()." {\n".implode("\n", $lines)."\n}";
    }

    private static function printEnum(EnumType $type): string
    {
        $lines = [];
        foreach ($type->values() as $value) {
            $lines[] = '  '.$value->getName();
        }

        return 'enum '.$type->name()." {\n".implode("\n", $lines)."\n}";
    }
}
