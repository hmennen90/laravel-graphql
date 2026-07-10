<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Tests\Fixtures\CanSchema;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

final class CanDirectiveTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('graphql.schema.factory', CanSchema::class);
    }

    public function test_denied_ability_produces_authorization_error(): void
    {
        Gate::define('view-secret', static fn ($user = null): bool => false);

        $this->postJson('/graphql', ['query' => '{ secret }'])
            ->assertOk()
            ->assertJsonPath('data.secret', null)
            ->assertJsonPath('errors.0.extensions.category', 'authorization');
    }

    public function test_allowed_ability_resolves_the_field(): void
    {
        Gate::define('view-secret', static fn ($user = null): bool => true);

        $this->postJson('/graphql', ['query' => '{ secret }'])
            ->assertOk()
            ->assertJsonPath('data.secret', 'classified');
    }

    public function test_unguarded_field_is_unaffected(): void
    {
        $this->postJson('/graphql', ['query' => '{ public }'])
            ->assertOk()
            ->assertJsonPath('data.public', 'open');
    }
}
