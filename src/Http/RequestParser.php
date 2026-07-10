<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http;

use Illuminate\Http\Request;

/**
 * Normalizes an incoming HTTP request into one or more GraphQL operations,
 * supporting single and batched (JSON array) payloads and GET query params.
 */
final class RequestParser
{
    private bool $batch = false;

    /**
     * @return array<int, array{query: string, variables: array<string, mixed>, operationName: ?string}>
     */
    public function parse(Request $request): array
    {
        $payload = $this->rawPayload($request);

        if (array_is_list($payload) && $payload !== []) {
            $this->batch = true;

            return array_map(fn (mixed $op): array => $this->normalize(is_array($op) ? $op : []), $payload);
        }

        $this->batch = false;

        return [$this->normalize($payload)];
    }

    public function isBatch(): bool
    {
        return $this->batch;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function rawPayload(Request $request): array
    {
        if ($request->isMethod('GET')) {
            return [
                'query' => $request->query('query'),
                'variables' => $request->query('variables'),
                'operationName' => $request->query('operationName'),
            ];
        }

        return $request->json()->all();
    }

    /**
     * @param  array<int|string, mixed>  $op
     * @return array{query: string, variables: array<string, mixed>, operationName: ?string}
     */
    private function normalize(array $op): array
    {
        $query = isset($op['query']) && is_string($op['query']) ? $op['query'] : '';

        $variables = $op['variables'] ?? [];
        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            $variables = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($variables)) {
            $variables = [];
        }

        $operationName = isset($op['operationName']) && is_string($op['operationName']) ? $op['operationName'] : null;

        /** @var array<string, mixed> $variables */
        return ['query' => $query, 'variables' => $variables, 'operationName' => $operationName];
    }
}
