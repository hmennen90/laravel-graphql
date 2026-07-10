<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL;

/** The result of {@see GraphQL::analyze()}: an operation's type and root field. */
final readonly class OperationAnalysis
{
    public function __construct(
        public bool $isSubscription,
        public ?string $rootField,
    ) {
    }
}
