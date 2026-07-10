<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Tests\Fixtures\CacheControlSchema;
use Hmennen90\GraphQL\Tests\TestCase;

final class CacheControlTest extends TestCase
{
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('graphql.schema.factory', CacheControlSchema::class);
        $app['config']->set('graphql.cache_control.enabled', true);
    }

    public function test_cache_control_header_uses_minimum_max_age(): void
    {
        $header = (string) $this->postJson('/graphql', ['query' => '{ profile { id name } }'])
            ->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringContainsString('max-age=30', $header);
        $this->assertStringContainsString('public', $header);
    }

    public function test_field_without_hint_is_uncacheable(): void
    {
        $header = (string) $this->postJson('/graphql', ['query' => '{ uncached }'])
            ->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $header);
    }
}
