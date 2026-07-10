<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Normalizes an incoming HTTP request into one or more GraphQL operations,
 * supporting single and batched (JSON array) payloads and GET query params.
 */
final class RequestParser
{
    private bool $batch = false;

    /**
     * @return array<int, array{query: string, variables: array<string, mixed>, operationName: ?string, extensions: array<string, mixed>}>
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

        if ($request->has('operations') && $request->has('map')) {
            return $this->parseMultipart($request);
        }

        return $request->json()->all();
    }

    /**
     * Parse a GraphQL multipart request (file uploads): the `operations` JSON, a
     * `map` from file field → variable paths, and the uploaded files.
     *
     * @return array<int|string, mixed>
     */
    private function parseMultipart(Request $request): array
    {
        $operationsInput = $request->input('operations');
        if (! is_string($operationsInput)) {
            return [];
        }
        $operations = json_decode($operationsInput, true);
        if (! is_array($operations)) {
            return [];
        }

        $mapInput = $request->input('map');
        $map = is_string($mapInput) ? json_decode($mapInput, true) : null;
        if (is_array($map)) {
            foreach ($map as $fileKey => $paths) {
                if (! is_array($paths)) {
                    continue;
                }
                $file = $request->file((string) $fileKey);
                foreach ($paths as $path) {
                    if (is_string($path)) {
                        Arr::set($operations, $path, $file);
                    }
                }
            }
        }

        return $operations;
    }

    /**
     * @param  array<int|string, mixed>  $op
     * @return array{query: string, variables: array<string, mixed>, operationName: ?string, extensions: array<string, mixed>}
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

        $extensions = $op['extensions'] ?? [];
        if (is_string($extensions)) {
            $decoded = json_decode($extensions, true);
            $extensions = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($extensions)) {
            $extensions = [];
        }

        $operationName = isset($op['operationName']) && is_string($op['operationName']) ? $op['operationName'] : null;

        /**
         * @var array<string, mixed> $variables
         * @var array<string, mixed> $extensions
         */
        return ['query' => $query, 'variables' => $variables, 'operationName' => $operationName, 'extensions' => $extensions];
    }
}
