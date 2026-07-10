<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Support;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Type\Definition\CustomScalarType;

/** Factory for the input-only `Upload` scalar used with the multipart spec. */
final class UploadType
{
    public static function make(): CustomScalarType
    {
        return new CustomScalarType(
            'Upload',
            serialize: static fn (): never => throw new CoercionError('`Upload` is an input-only scalar and cannot be serialized.'),
            parseValue: static fn (mixed $value): mixed => $value,
            parseLiteral: static fn (): never => throw new CoercionError('`Upload` must be provided as a variable via a multipart request.'),
            description: 'The `Upload` scalar type represents a file upload.',
        );
    }
}
