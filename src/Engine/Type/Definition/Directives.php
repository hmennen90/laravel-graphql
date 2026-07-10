<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** Factory for the built-in directives (`@skip`, `@include`, `@deprecated`). */
final class Directives
{
    private static ?Directive $include = null;

    private static ?Directive $skip = null;

    private static ?Directive $deprecated = null;

    public static function include(): Directive
    {
        return self::$include ??= new Directive(
            'include',
            ['FIELD', 'FRAGMENT_SPREAD', 'INLINE_FRAGMENT'],
            [Argument::make('if', Type::nonNull(Type::boolean()))],
            description: 'Directs the executor to include this field or fragment only when the `if` argument is true.',
        );
    }

    public static function skip(): Directive
    {
        return self::$skip ??= new Directive(
            'skip',
            ['FIELD', 'FRAGMENT_SPREAD', 'INLINE_FRAGMENT'],
            [Argument::make('if', Type::nonNull(Type::boolean()))],
            description: 'Directs the executor to skip this field or fragment when the `if` argument is true.',
        );
    }

    public static function deprecated(): Directive
    {
        return self::$deprecated ??= new Directive(
            'deprecated',
            ['FIELD_DEFINITION', 'ENUM_VALUE', 'ARGUMENT_DEFINITION', 'INPUT_FIELD_DEFINITION'],
            [Argument::withDefault('reason', Type::string(), 'No longer supported')],
            description: 'Marks an element of a GraphQL schema as no longer supported.',
        );
    }

    /**
     * @return array<int, Directive>
     */
    public static function all(): array
    {
        return [self::include(), self::skip(), self::deprecated()];
    }
}
