<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http\Controllers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/** Serves the in-browser GraphiQL IDE. */
final readonly class GraphiQLController
{
    public function __construct(
        private ViewFactory $views,
        private Repository $config,
    ) {
    }

    public function index(): View
    {
        $uri = $this->config->get('graphql.route.uri', '/graphql');

        return $this->views->make('graphql::graphiql', [
            'endpoint' => is_string($uri) ? $uri : '/graphql',
        ]);
    }
}
