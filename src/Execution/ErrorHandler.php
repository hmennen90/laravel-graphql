<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Execution;

use Hmennen90\GraphQL\Engine\Error\GraphQLError;
use Hmennen90\GraphQL\Exceptions\AuthorizationError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Formats engine errors into the GraphQL error shape, masking internal
 * exception messages unless debug mode is on or the exception is client-safe.
 */
final readonly class ErrorHandler
{
    /**
     * @param  array<int, string>  $safeExceptions  Fully-qualified exception class names treated as client-safe.
     */
    public function __construct(
        private bool $debug,
        private array $safeExceptions = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function format(GraphQLError $error): array
    {
        $formatted = $error->toArray();
        $previous = $error->getPrevious();

        /** @var array<string, mixed> $extensions */
        $extensions = is_array($formatted['extensions'] ?? null) ? $formatted['extensions'] : [];

        $category = $this->categorize($previous);
        if ($category !== null) {
            $extensions['category'] = $category;
        }

        if ($previous instanceof ValidationException) {
            $extensions['validation'] = $previous->errors();
        }

        if ($this->debug && $previous !== null) {
            $extensions['debug'] = [
                'exception' => $previous::class,
                'message' => $previous->getMessage(),
            ];
        }

        if (! $this->debug && $previous !== null && ! $this->isSafe($previous)) {
            $formatted['message'] = 'Internal server error';
        }

        if ($extensions !== []) {
            $formatted['extensions'] = $extensions;
        }

        return $formatted;
    }

    private function categorize(?Throwable $previous): ?string
    {
        return match (true) {
            $previous instanceof AuthorizationError,
            $previous instanceof AuthorizationException => 'authorization',
            $previous instanceof AuthenticationException => 'authentication',
            $previous instanceof ValidationException => 'validation',
            default => null,
        };
    }

    private function isSafe(Throwable $previous): bool
    {
        return array_any($this->safeExceptions, fn($safe) => $previous instanceof $safe);
    }
}
