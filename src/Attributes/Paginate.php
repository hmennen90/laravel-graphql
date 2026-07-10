<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Attributes;

use Attribute;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;

/** Code-first equivalent of `@paginate(type: PAGINATOR|CONNECTION)`. */
#[Attribute(Attribute::TARGET_METHOD)]
final class Paginate extends DirectiveAttribute
{
    public function __construct(
        private readonly ?string $type = null,
        private readonly ?string $model = null,
    ) {}

    #[\Override]
    public function toDirectiveNode(): DirectiveNode
    {
        return $this->node('paginate', ['type' => $this->type, 'model' => $this->model]);
    }
}
