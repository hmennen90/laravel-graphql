<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Directives;

use Closure;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\DirectiveMiddleware;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Executor\ResolveInfo;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\Directive;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class UppercaseMiddleware implements DirectiveMiddleware
{
    public function handle(DirectiveNode $node, ResolveInfo $info, Closure $resolve): mixed
    {
        $value = $resolve();

        return is_string($value) ? strtoupper($value) : $value;
    }
}

final class UppercaseSchemaDirective implements SchemaDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node): FieldDefinition
    {
        $original = $field->getResolver();

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function (mixed $s, array $a, mixed $c, ResolveInfo $i) use ($original): mixed {
                $value = $original !== null ? $original($s, $a, $c, $i) : null;

                return is_string($value) ? strtoupper($value) : $value;
            },
            array_values($field->args()),
        );
    }
}

final class CustomDirectivesTest extends TestCase
{
    public function test_runtime_directive_middleware(): void
    {
        $query = new ObjectType('Query', [
            FieldDefinition::make('hello', Type::string(), resolve: fn (): string => 'world'),
        ]);

        $schema = new Schema(new SchemaConfig(
            query: $query,
            directives: [new Directive('upper', ['FIELD'], [Argument::make('_', Type::string())])],
            directiveMiddleware: ['upper' => new UppercaseMiddleware()],
        ));

        $result = Executor::execute($schema, Parser::parse('{ hello @upper }'))->toArray();

        $this->assertSame(['data' => ['hello' => 'WORLD']], $result);
    }

    public function test_build_time_schema_directive(): void
    {
        $schema = SchemaBuilder::fromSdl(
            <<<'GRAPHQL'
                directive @upper on FIELD_DEFINITION
                type Query { shout: String @upper }
                GRAPHQL,
            resolvers: ['Query' => ['shout' => fn (): string => 'hi']],
            schemaDirectives: ['upper' => new UppercaseSchemaDirective()],
        );

        $result = Executor::execute($schema, Parser::parse('{ shout }'))->toArray();

        $this->assertSame(['data' => ['shout' => 'HI']], $result);
    }
}
