<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Executor\DefaultFieldResolver;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Execution\Context;

/**
 * `@inject(context: "user.id", name: "user_id")` — injects a value from the request
 * context into a field argument before resolution (e.g. the authenticated user id).
 * Place it after the resolver directive, e.g. `@create @inject(...)`.
 */
final readonly class InjectDirective implements SchemaDirective
{
    use ReadsArguments;

    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $inner = $field->getResolver() ?? new DefaultFieldResolver();
        $path = $this->stringArg($node, 'context') ?? '';
        $name = $this->stringArg($node, 'name') ?? '';

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function (mixed $source, array $args, mixed $ctx, mixed $info) use ($inner, $path, $name): mixed {
                if ($ctx instanceof Context && $name !== '' && str_starts_with($path, 'user.')) {
                    $args[$name] = data_get($ctx->user, substr($path, 5));
                }

                return $inner($source, $args, $ctx, $info);
            },
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
