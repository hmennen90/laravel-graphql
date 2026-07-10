<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Attributes;

use Attribute;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;

/**
 * Code-first equivalent of the Eloquent relation directives. The directive name
 * (`hasMany`, `hasOne`, `belongsTo`, `belongsToMany`, `morphMany`, …) selects the
 * relation kind; `relation:` overrides the relation name (defaults to the field).
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Relation extends DirectiveAttribute
{
    public function __construct(
        private readonly string $type = 'hasMany',
        private readonly ?string $relation = null,
    ) {}

    #[\Override]
    public function toDirectiveNode(): DirectiveNode
    {
        return $this->node($this->type, ['relation' => $this->relation]);
    }
}
