<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Illuminate\Contracts\Container\Container;

/**
 * `@field(resolver: "App\\GraphQL\\Foo@bar")` — binds a field to a resolver method
 * on a container-resolved class (defaults to `__invoke`).
 */
final readonly class FieldResolverDirective implements SchemaDirective
{
    use ReadsArguments;

    public function __construct(private Container $container)
    {
    }

    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $spec = $this->stringArg($node, 'resolver') ?? '';
        [$class, $method] = array_pad(explode('@', $spec, 2), 2, '__invoke');
        $container = $this->container;

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            function (mixed $source, array $args, mixed $ctx, mixed $info) use ($container, $class, $method): mixed {
                if ($class === '') {
                    return null;
                }
                $instance = $container->make($class);

                return is_object($instance) ? $instance->{$method}($source, $args, $ctx, $info) : null;
            },
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
