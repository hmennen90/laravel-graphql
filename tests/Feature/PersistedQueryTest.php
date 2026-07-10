<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Tests\TestCase;

final class PersistedQueryTest extends TestCase
{
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('graphql.persisted_queries.enabled', true);
        $app['config']->set('cache.default', 'array');
    }

    public function test_hash_only_before_registration_is_a_miss(): void
    {
        $this->postJson('/graphql', [
            'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => str_repeat('a', 64)]],
        ])
            ->assertOk()
            ->assertJsonPath('errors.0.extensions.code', 'PERSISTED_QUERY_NOT_FOUND');
    }

    public function test_register_then_replay_by_hash(): void
    {
        $query = '{ hello }';
        $hash = hash('sha256', $query);

        // 1. register (query + hash)
        $this->postJson('/graphql', [
            'query' => $query,
            'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => $hash]],
        ])->assertOk()->assertExactJson(['data' => ['hello' => 'world']]);

        // 2. replay by hash only
        $this->postJson('/graphql', [
            'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => $hash]],
        ])->assertOk()->assertExactJson(['data' => ['hello' => 'world']]);
    }

    public function test_hash_mismatch_is_rejected(): void
    {
        $this->postJson('/graphql', [
            'query' => '{ hello }',
            'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => str_repeat('b', 64)]],
        ])
            ->assertOk()
            ->assertJsonPath('errors.0.extensions.code', 'PERSISTED_QUERY_HASH_MISMATCH');
    }
}
