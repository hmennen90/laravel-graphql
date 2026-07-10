<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Support;

use Hmennen90\GraphQL\Engine\Type\Definition\CustomScalarType;

/** Factory for an untyped `JSON` scalar (arbitrary JSON-serialisable value). */
final class JsonType
{
    private static ?CustomScalarType $instance = null;

    public static function make(): CustomScalarType
    {
        return self::$instance ??= new CustomScalarType(
            'JSON',
            serialize: static fn (mixed $value): mixed => $value,
            parseValue: static fn (mixed $value): mixed => $value,
            description: 'Arbitrary JSON-serialisable data.',
        );
    }
}
