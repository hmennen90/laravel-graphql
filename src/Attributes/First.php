<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Attributes;

use Attribute;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;

/** Code-first equivalent of `@first`. */
#[Attribute(Attribute::TARGET_METHOD)]
final class First extends DirectiveAttribute
{
    public function __construct(private readonly ?string $model = null) {}

    #[\Override]
    public function toDirectiveNode(): DirectiveNode
    {
        return $this->node('first', ['model' => $this->model]);
    }
}
