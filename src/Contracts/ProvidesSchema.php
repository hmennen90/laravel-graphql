<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Contracts;

use Hmennen90\GraphQL\Engine\Schema\Schema;

/** Implemented by application classes that build the GraphQL schema. */
interface ProvidesSchema
{
    public function schema(): Schema;
}
