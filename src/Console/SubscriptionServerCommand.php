<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Hmennen90\GraphQL\Subscriptions\GraphqlWs\SubscriptionServer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;

/** Boots the graphql-ws WebSocket server (requires a bound {@see SubscriptionServer} driver). */
final class SubscriptionServerCommand extends Command
{
    protected $signature = 'graphql:subscriptions:serve {--host=0.0.0.0} {--port=9501}';

    protected $description = 'Run the graphql-ws subscription WebSocket server.';

    public function handle(Container $container): int
    {
        if (! $container->bound(SubscriptionServer::class)) {
            $this->error('No graphql-ws server driver is available. Install the Swoole/OpenSwoole extension (ext-swoole).');

            return self::FAILURE;
        }

        $host = is_string($this->option('host')) ? $this->option('host') : '0.0.0.0';
        $port = (int) $this->option('port');

        $server = $container->make(SubscriptionServer::class);
        $this->info(sprintf('graphql-ws server listening on ws://%s:%d', $host, $port));
        $server->run($host, $port);

        return self::SUCCESS;
    }
}
