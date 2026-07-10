<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Execution\Context;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

final class OkResolver
{
    public function __invoke(): string
    {
        return 'ok';
    }
}

final class EchoUserIdResolver
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $source, array $args): mixed
    {
        return $args['user_id'] ?? null;
    }
}

final class UtilityDirectiveTest extends TestCase
{
    private function schema(): Schema
    {
        $sdl = 'type Query {'
            .'   ok: String @field(resolver: "'.addslashes(OkResolver::class).'") @guard'
            .'   whoami: ID @field(resolver: "'.addslashes(EchoUserIdResolver::class).'") @inject(context: "user.id", name: "user_id")'
            .' }';

        return SchemaBuilder::fromSdl($sdl, schemaDirectives: $this->app->make(DirectiveRegistry::class)->all());
    }

    private function context(?Authenticatable $user): Context
    {
        return new Context(request(), $user, $this->app->make(Gate::class));
    }

    public function test_guard_denies_unauthenticated(): void
    {
        $result = Executor::execute($this->schema(), Parser::parse('{ ok }'), null, $this->context(null))->toArray();

        $this->assertNull($result['data']['ok']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_guard_allows_and_inject_sets_user_id(): void
    {
        $user = new GenericUser(['id' => 7]);

        $result = Executor::execute($this->schema(), Parser::parse('{ ok whoami }'), null, $this->context($user))->toArray();

        $this->assertSame('ok', $result['data']['ok']);
        $this->assertSame('7', $result['data']['whoami']);
    }
}
