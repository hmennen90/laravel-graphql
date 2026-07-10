<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        // Requires the Swoole extension; not analysable in a standard environment.
        __DIR__.'/src/Subscriptions/Swoole',
        // Benchmarks reference optional dev-only packages (nuwave/lighthouse, webonyx).
        __DIR__.'/tests/Benchmark',
    ])
    // Target the PHP version declared in composer.json (^8.4).
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
    );
