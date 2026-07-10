<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

use Closure;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;

/**
 * Runtime behaviour for a custom query directive on a field. Registered on the
 * schema by directive name; the executor wraps field resolution through it.
 */
interface DirectiveMiddleware
{
    /**
     * @param  Closure(): mixed  $resolve  produces the field's resolved value
     */
    public function handle(DirectiveNode $node, ResolveInfo $info, Closure $resolve): mixed;
}
