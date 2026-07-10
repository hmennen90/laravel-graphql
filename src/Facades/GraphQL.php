<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Facades;

use Hmennen90\GraphQL\Engine\Executor\ExecutionResult;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Schema schema()
 * @method static ExecutionResult execute(string $query, array<string, mixed> $variables = [], ?string $operationName = null, mixed $context = null)
 *
 * @see \Hmennen90\GraphQL\GraphQL
 */
final class GraphQL extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hmennen90\GraphQL\GraphQL::class;
    }
}
