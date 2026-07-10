<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Execution;

use Hmennen90\GraphQL\Exceptions\AuthorizationError;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * The per-request execution context handed to resolvers. Exposes the request,
 * the authenticated user and a Gate bridge for field-level authorization.
 */
final class Context
{
    public function __construct(
        public readonly Request $request,
        public readonly ?Authenticatable $user,
        private readonly Gate $gate,
    ) {
    }

    /**
     * Authorize an ability for the current user, throwing an {@see AuthorizationError}
     * (a client-safe error) when denied.
     */
    public function authorize(string $ability, mixed ...$arguments): void
    {
        if (! $this->gate->forUser($this->user)->allows($ability, $arguments)) {
            throw new AuthorizationError(sprintf('Not authorized to perform "%s".', $ability));
        }
    }

    public function allows(string $ability, mixed ...$arguments): bool
    {
        return $this->gate->forUser($this->user)->allows($ability, $arguments);
    }
}
