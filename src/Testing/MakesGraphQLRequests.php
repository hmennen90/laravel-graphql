<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Testing;

use Illuminate\Testing\TestResponse;

/**
 * Feature-test helper with a Lighthouse-compatible API: POST a GraphQL operation to
 * the configured endpoint and get back a {@see TestResponse} to assert on. Mix it
 * into a Laravel/testbench test case.
 *
 * @mixin \Illuminate\Foundation\Testing\Concerns\MakesHttpRequests
 */
trait MakesGraphQLRequests
{
    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, mixed>  $headers
     */
    protected function graphQL(string $query, array $variables = [], array $headers = []): TestResponse
    {
        return $this->postGraphQL(['query' => $query, 'variables' => $variables], $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $headers
     */
    protected function postGraphQL(array $data, array $headers = []): TestResponse
    {
        return $this->postJson($this->graphQLEndpointUrl(), $data, $headers);
    }

    protected function graphQLEndpointUrl(): string
    {
        $uri = config('graphql.route.uri');

        return is_string($uri) ? $uri : '/graphql';
    }
}
