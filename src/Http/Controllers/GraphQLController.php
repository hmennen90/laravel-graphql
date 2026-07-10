<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http\Controllers;

use Hmennen90\GraphQL\Execution\Context;
use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\CacheControlAnalyzer;
use Hmennen90\GraphQL\Http\PersistedQueryException;
use Hmennen90\GraphQL\Http\PersistedQueryResolver;
use Hmennen90\GraphQL\Http\RequestParser;
use Hmennen90\GraphQL\Http\ResponseBuilder;
use Hmennen90\GraphQL\Subscriptions\SubscriptionManager;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The HTTP entry point: accepts single or batched operations and returns JSON. */
final readonly class GraphQLController
{
    public function __construct(
        private GraphQL $graphql,
        private ResponseBuilder $responses,
        private Gate $gate,
        private Repository $config,
        private SubscriptionManager $subscriptions,
        private PersistedQueryResolver $persistedQueries,
        private CacheControlAnalyzer $cacheControl,
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
            try {
                $operation['query'] = $this->persistedQueries->resolve($operation['query'], $operation['extensions']);
            } catch (PersistedQueryException $e) {
                $results[] = ['errors' => [['message' => $e->getMessage(), 'extensions' => ['code' => $e->errorCode]]]];

                continue;
            }

            if ($operation['query'] === '') {
                $results[] = ['errors' => [['message' => 'No GraphQL query was provided.']]];

                continue;
            }

            $analysis = $this->graphql->analyze($operation['query'], $operation['operationName']);
            if ($analysis->isSubscription) {
                $results[] = $this->subscribe($operation, $analysis->rootField);

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

        $response = new JsonResponse($parser->isBatch() ? $results : $results[0]);

        if (! $parser->isBatch() && $this->config->get('graphql.cache_control.enabled') === true && ! isset($results[0]['errors'])) {
            $hint = $this->cacheControl->analyze($this->graphql->schema(), $operations[0]['query'], $operations[0]['operationName']);
            if ($hint !== null) {
                $response->headers->set('Cache-Control', $hint['maxAge'] > 0
                    ? sprintf('%s, max-age=%d', strtolower($hint['scope']), $hint['maxAge'])
                    : 'no-store');
            }
        }

        return $response;
    }

    /**
     * @param  array{query: string, variables: array<string, mixed>, operationName: ?string, extensions: array<string, mixed>}  $operation
     * @return array<string, mixed>
     */
    private function subscribe(array $operation, ?string $rootField): array
    {
        if ($this->config->get('graphql.subscriptions.enabled') !== true) {
            return ['errors' => [['message' => 'Subscriptions are not enabled.']]];
        }

        if ($rootField === null) {
            return ['errors' => [['message' => 'Could not determine the subscription field.']]];
        }

        $subscriber = $this->subscriptions->register(
            $rootField,
            $operation['query'],
            $operation['variables'],
            $operation['operationName'],
        );

        return [
            'data' => null,
            'extensions' => ['subscription' => ['channel' => $subscriber->channel]],
        ];
    }
}
