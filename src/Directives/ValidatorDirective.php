<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Executor\DefaultFieldResolver;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Validator;

/**
 * `@validator(class: "App\\GraphQL\\Validators\\CreateUserValidator")` — validates
 * all field arguments with a dedicated validator class. The class must expose a
 * `rules(): array` method (and optionally `messages(): array`); a failure throws a
 * `ValidationException`.
 */
final readonly class ValidatorDirective implements SchemaDirective
{
    use ReadsArguments;

    public function __construct(private Container $container) {}

    #[\Override]
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $class = $this->stringArg($node, 'class') ?? '';
        $inner = $field->getResolver() ?? new DefaultFieldResolver();
        $container = $this->container;

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function (mixed $source, array $args, mixed $ctx, mixed $info) use ($inner, $class, $container): mixed {
                if ($class !== '' && class_exists($class)) {
                    $validator = $container->make($class);
                    $rules = is_object($validator) && method_exists($validator, 'rules') ? $validator->rules() : [];
                    $messages = is_object($validator) && method_exists($validator, 'messages') ? $validator->messages() : [];
                    Validator::make($args, is_array($rules) ? $rules : [], is_array($messages) ? $messages : [])->validate();
                }

                return $inner($source, $args, $ctx, $info);
            },
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
