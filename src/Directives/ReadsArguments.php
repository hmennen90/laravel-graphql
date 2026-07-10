<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;

/** Small helper to read arguments from a directive node. */
trait ReadsArguments
{
    protected function stringArg(DirectiveNode $node, string $name): ?string
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name === $name && $argument->value instanceof StringValueNode) {
                return $argument->value->value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function stringListArg(DirectiveNode $node, string $name): array
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name !== $name || ! $argument->value instanceof ListValueNode) {
                continue;
            }

            $values = [];
            foreach ($argument->value->values as $value) {
                if ($value instanceof StringValueNode) {
                    $values[] = $value->value;
                }
            }

            return $values;
        }

        return [];
    }
}
