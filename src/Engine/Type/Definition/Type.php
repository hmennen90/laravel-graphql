<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Hmennen90\GraphQL\Engine\Type\Scalars\BooleanType;
use Hmennen90\GraphQL\Engine\Type\Scalars\FloatType;
use Hmennen90\GraphQL\Engine\Type\Scalars\IDType;
use Hmennen90\GraphQL\Engine\Type\Scalars\IntType;
use Hmennen90\GraphQL\Engine\Type\Scalars\StringType;

/**
 * Base class for every type in the system. Also the entry point for the
 * built-in scalars and the list/non-null wrapping constructors.
 */
abstract class Type implements \Stringable
{
    private static ?IntType $int = null;

    private static ?FloatType $float = null;

    private static ?StringType $string = null;

    private static ?BooleanType $boolean = null;

    private static ?IDType $id = null;

    abstract public function toString(): string;

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function int(): IntType
    {
        return self::$int ??= new IntType();
    }

    public static function float(): FloatType
    {
        return self::$float ??= new FloatType();
    }

    public static function string(): StringType
    {
        return self::$string ??= new StringType();
    }

    public static function boolean(): BooleanType
    {
        return self::$boolean ??= new BooleanType();
    }

    public static function id(): IDType
    {
        return self::$id ??= new IDType();
    }

    /**
     * The five built-in scalars, keyed by name.
     *
     * @return array<string, ScalarType>
     */
    public static function builtInScalars(): array
    {
        return [
            'Int' => self::int(),
            'Float' => self::float(),
            'String' => self::string(),
            'Boolean' => self::boolean(),
            'ID' => self::id(),
        ];
    }

    public static function nonNull(Type $type): NonNull
    {
        return new NonNull($type);
    }

    public static function listOf(Type $type): ListOfType
    {
        return new ListOfType($type);
    }

    /** Unwrap list/non-null wrappers to the underlying named type. */
    public static function getNamedType(Type $type): NamedType
    {
        while ($type instanceof WrappingType) {
            $type = $type->wrappedType();
        }

        if (! $type instanceof NamedType) {
            throw new \LogicException('Unwrapped type is not a named type.');
        }

        return $type;
    }

    /** Unwrap a leading non-null, if any. */
    public static function getNullableType(Type $type): Type
    {
        return $type instanceof NonNull ? $type->wrappedType() : $type;
    }
}
