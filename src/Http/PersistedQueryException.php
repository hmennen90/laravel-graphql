<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http;

use RuntimeException;

/** Raised for Automatic Persisted Query protocol failures (miss / hash mismatch). */
final class PersistedQueryException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }
}
