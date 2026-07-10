<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Support\Relay\Relay;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Support\Facades\Hash;

final class SampleValidator
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'min:3']];
    }
}

final class ArgumentDirectiveTest extends TestCase
{
    /**
     * @param  array<string, array<string, callable>>  $resolvers
     */
    private function schema(string $sdl, array $resolvers): Schema
    {
        return SchemaBuilder::fromSdl(
            $sdl,
            resolvers: $resolvers,
            schemaDirectives: $this->app->make(DirectiveRegistry::class)->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function exec(Schema $schema, string $query, array $variables = []): array
    {
        return Executor::execute($schema, Parser::parse($query), variableValues: $variables)->toArray();
    }

    public function test_trim_sanitises_a_string_argument(): void
    {
        $schema = $this->schema(
            'type Query { echo(text: String @trim): String }',
            ['Query' => ['echo' => static fn ($root, array $args): mixed => $args['text'] ?? null]],
        );

        $result = $this->exec($schema, '{ echo(text: "  spaced  ") }');

        $this->assertSame('spaced', $result['data']['echo']);
    }

    public function test_hash_hashes_a_string_argument(): void
    {
        $schema = $this->schema(
            'type Query { check(secret: String @hash): Boolean }',
            ['Query' => ['check' => static fn ($root, array $args): bool => is_string($args['secret'] ?? null) && Hash::check('pw', $args['secret'])]],
        );

        $result = $this->exec($schema, '{ check(secret: "pw") }');

        $this->assertTrue($result['data']['check']);
    }

    public function test_global_id_decodes_to_the_raw_id(): void
    {
        $schema = $this->schema(
            'type Query { raw(id: ID @globalId): ID }',
            ['Query' => ['raw' => static fn ($root, array $args): mixed => $args['id'] ?? null]],
        );

        $result = $this->exec($schema, '{ raw(id: "'.Relay::toGlobalId('User', 42).'") }');

        $this->assertSame('42', $result['data']['raw']);
    }

    public function test_rules_rejects_invalid_input(): void
    {
        $schema = $this->schema(
            'type Query { reg(email: String @rules(apply: ["email"])): String }',
            ['Query' => ['reg' => static fn ($root, array $args): mixed => $args['email'] ?? null]],
        );

        $this->assertSame('a@b.com', $this->exec($schema, '{ reg(email: "a@b.com") }')['data']['reg']);

        $invalid = $this->exec($schema, '{ reg(email: "not-an-email") }');
        $this->assertNotEmpty($invalid['errors']);
        $this->assertNull($invalid['data']['reg']);
    }

    public function test_validator_class_validates_all_arguments(): void
    {
        $sdl = 'type Query { ping: String } type Mutation { make(name: String): String @validator(class: "'.addslashes(SampleValidator::class).'") }';
        $schema = $this->schema(
            $sdl,
            ['Mutation' => ['make' => static fn ($root, array $args): mixed => $args['name'] ?? null]],
        );

        $this->assertSame('abc', $this->exec($schema, 'mutation { make(name: "abc") }')['data']['make']);

        $invalid = $this->exec($schema, 'mutation { make(name: "ab") }');
        $this->assertNotEmpty($invalid['errors']);
    }
}
