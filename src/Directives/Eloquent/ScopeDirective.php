<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Closure;
use Hmennen90\GraphQL\Directives\ReadsArguments;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgBuilderDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Illuminate\Database\Eloquent\Builder;

/** `@scope(name:)` — applies a local Eloquent scope, passing the argument value. */
final readonly class ScopeDirective implements ArgBuilderDirective
{
    use ReadsArguments;

    public function toBuilder(DirectiveNode $node, string $argName): Closure
    {
        $scope = $this->stringArg($node, 'name') ?? $argName;

        return static function (mixed $builder, mixed $value) use ($scope): mixed {
            if (! $builder instanceof Builder) {
                return $builder;
            }

            // Scopes are magic methods (__call), so dispatch through an object type.
            return self::callScope($builder, $scope, $value);
        };
    }

    private static function callScope(object $builder, string $scope, mixed $value): mixed
    {
        return $builder->{$scope}($value);
    }
}
