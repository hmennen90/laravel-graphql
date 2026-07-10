<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** A type that wraps another (list or non-null). */
interface WrappingType
{
    public function wrappedType(): Type;
}
