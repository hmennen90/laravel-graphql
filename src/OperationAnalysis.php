<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL;

/** The result of {@see GraphQL::analyze()}: an operation's type and root field. */
final class OperationAnalysis
{
    public function __construct(
        public readonly bool $isSubscription,
        public readonly ?string $rootField,
    ) {
    }
}
