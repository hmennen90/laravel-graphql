<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes;

use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;

/**
 * Implemented by PHP attributes that are the code-first equivalent of an SDL
 * directive (e.g. `#[All]` ≡ `@all`). The attribute converts itself into the same
 * {@see DirectiveNode} the SDL parser would produce, so both surfaces dispatch to
 * the exact same directive implementation.
 */
interface ProvidesDirective
{
    public function toDirectiveNode(): DirectiveNode;
}
