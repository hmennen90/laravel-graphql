<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http\Controllers;

use Hmennen90\GraphQL\Execution\Context;
use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\RequestParser;
use Hmennen90\GraphQL\Http\ResponseBuilder;
use Hmennen90\GraphQL\Subscriptions\SubscriptionManager;
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
        private readonly SubscriptionManager $subscriptions,
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

        return new JsonResponse($parser->isBatch() ? $results : $results[0]);
    }

    /**
     * @param  array{query: string, variables: array<string, mixed>, operationName: ?string}  $operation
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
