<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** A type that has a name (i.e. not a list/non-null wrapper). */
interface NamedType
{
    public function name(): string;

    public function description(): ?string;
}
