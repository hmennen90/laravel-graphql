<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Executor\DefaultFieldResolver;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Execution\Context;
use Illuminate\Auth\AuthenticationException;

/** `@guard` — requires an authenticated user before the field resolves. */
final readonly class GuardDirective implements SchemaDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $inner = $field->getResolver() ?? new DefaultFieldResolver();

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function (mixed $source, array $args, mixed $ctx, mixed $info) use ($inner): mixed {
                if (! $ctx instanceof Context || $ctx->user === null) {
                    throw new AuthenticationException();
                }

                return $inner($source, $args, $ctx, $info);
            },
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
