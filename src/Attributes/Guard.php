<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Attributes;

use Attribute;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;

/** Code-first equivalent of `@guard`. */
#[Attribute(Attribute::TARGET_METHOD)]
final class Guard extends DirectiveAttribute
{
    #[\Override]
    public function toDirectiveNode(): DirectiveNode
    {
        return $this->node('guard');
    }
}
