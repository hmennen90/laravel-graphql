<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Attributes;

use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\ProvidesDirective;
use Hmennen90\GraphQL\Engine\Language\AST\ArgumentNode;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;

/**
 * Base for code-first directive attributes. Subclasses declare their directive
 * name and typed constructor arguments; this class turns them into the matching
 * {@see DirectiveNode} (all values as string arguments, which every directive
 * reads via its `stringArg()` helper).
 */
abstract class DirectiveAttribute implements ProvidesDirective
{
    /**
     * @param  array<string, string|null>  $arguments
     */
    protected function node(string $name, array $arguments = []): DirectiveNode
    {
        $nodes = [];
        foreach ($arguments as $key => $value) {
            if ($value !== null) {
                $nodes[] = new ArgumentNode($key, new StringValueNode($value));
            }
        }

        return new DirectiveNode($name, $nodes);
    }
}
