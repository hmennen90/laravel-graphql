<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http\Controllers;

use Hmennen90\GraphQL\Execution\Context;
use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\RequestParser;
use Hmennen90\GraphQL\Http\ResponseBuilder;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The HTTP entry point: accepts single or batched operations and returns JSON. */
final class GraphQLController
{
    public function __construct(
        private readonly GraphQL $graphql,
        private readonly ResponseBuilder $responses,
        private readonly Gate $gate,
        private readonly Repository $config,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $parser = new RequestParser();
        $operations = $parser->parse($request);

        if ($parser->isBatch()) {
            if ($this->config->get('graphql.batching.enabled') !== true) {
                return new JsonResponse(['errors' => [['message' => 'Query batching is not enabled.']]], 400);
            }
            $maxConfig = $this->config->get('graphql.batching.max', 10);
            $max = is_int($maxConfig) ? $maxConfig : 10;
            if (count($operations) > $max) {
                return new JsonResponse(['errors' => [['message' => sprintf('Batch exceeds the maximum of %d operations.', $max)]]], 400);
            }
        }

        $context = new Context($request, $request->user(), $this->gate);

        $results = [];
        foreach ($operations as $operation) {
            if ($operation['query'] === '') {
                $results[] = ['errors' => [['message' => 'No GraphQL query was provided.']]];

                continue;
            }

            $result = $this->graphql->execute(
                $operation['query'],
                $operation['variables'],
                $operation['operationName'],
                $context,
            );
            $results[] = $this->responses->build($result);
        }

        return new JsonResponse($parser->isBatch() ? $results : $results[0]);
    }
}
