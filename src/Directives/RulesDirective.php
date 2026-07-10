<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Closure;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgumentDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Illuminate\Support\Facades\Validator;

/**
 * `@rules(apply: ["required", "email"])` — validates a single argument value with
 * Laravel's validator before the field resolves; a failure throws a
 * `ValidationException`, which the HTTP layer renders as a GraphQL error.
 */
final readonly class RulesDirective implements ArgumentDirective
{
    use ReadsArguments;

    #[\Override]
    public function applyToArgument(Argument $argument, DirectiveNode $node, SchemaBuildContext $context): Closure
    {
        $rules = $this->stringListArg($node, 'apply');
        $attribute = $argument->getName();

        return static function (mixed $value) use ($rules, $attribute): mixed {
            if ($rules !== []) {
                Validator::make([$attribute => $value], [$attribute => $rules])->validate();
            }

            return $value;
        };
    }
}
