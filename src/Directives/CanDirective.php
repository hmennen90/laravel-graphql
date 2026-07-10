<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Executor\DefaultFieldResolver;
use Hmennen90\GraphQL\Engine\Executor\ResolveInfo;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Exceptions\AuthorizationError;
use Hmennen90\GraphQL\Execution\Context;

/**
 * SDL directive `@can(ability: "…")` that gates a field behind a Laravel Gate
 * ability, checked against the request's {@see Context} before resolution.
 */
final class CanDirective implements SchemaDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, \Hmennen90\GraphQL\Engine\Building\SchemaBuildContext $context): FieldDefinition
    {
        $ability = $this->ability($node);
        $resolver = $field->getResolver() ?? new DefaultFieldResolver();

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function (mixed $source, array $args, mixed $context, ResolveInfo $info) use ($ability, $resolver): mixed {
                if ($context instanceof Context && $ability !== '' && ! $context->allows($ability)) {
                    throw new AuthorizationError(sprintf('Not authorized to perform "%s".', $ability));
                }

                return $resolver($source, $args, $context, $info);
            },
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }

    private function ability(DirectiveNode $node): string
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name === 'ability' && $argument->value instanceof StringValueNode) {
                return $argument->value->value;
            }
        }

        return '';
    }
}
