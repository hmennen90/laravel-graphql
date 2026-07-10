<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\EnumValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;

/**
 * SDL directive `@cacheControl(maxAge: Int, scope: String)` that records cache
 * hints on a field as metadata for {@see \Hmennen90\GraphQL\Http\CacheControlAnalyzer}.
 */
final class CacheControlDirective implements SchemaDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, \Hmennen90\GraphQL\Engine\Building\SchemaBuildContext $context): FieldDefinition
    {
        $metadata = [
            'cacheMaxAge' => $this->maxAge($node),
            'cacheScope' => $this->scope($node),
        ];

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            $field->getResolver(),
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
            $metadata,
        );
    }

    private function maxAge(DirectiveNode $node): ?int
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name === 'maxAge' && $argument->value instanceof IntValueNode) {
                return (int) $argument->value->value;
            }
        }

        return null;
    }

    private function scope(DirectiveNode $node): string
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name !== 'scope') {
                continue;
            }
            if ($argument->value instanceof StringValueNode || $argument->value instanceof EnumValueNode) {
                return strtoupper($argument->value->value);
            }
        }

        return 'PUBLIC';
    }
}
