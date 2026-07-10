<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Tests\TestCase;

final class ErrorMaskingTest extends TestCase
{
    public function test_internal_exceptions_are_masked_when_debug_is_off(): void
    {
        config()->set('graphql.debug', false);

        $response = $this->postJson('/graphql', ['query' => '{ boom }'])->assertOk();

        $response->assertJsonPath('data.boom', null);
        $this->assertSame('Internal server error', $response->json('errors.0.message'));
    }

    public function test_internal_exceptions_are_visible_when_debug_is_on(): void
    {
        config()->set('graphql.debug', true);

        $response = $this->postJson('/graphql', ['query' => '{ boom }'])->assertOk();

        $this->assertStringContainsString('internal detail leak', $response->json('errors.0.message'));
    }
}
