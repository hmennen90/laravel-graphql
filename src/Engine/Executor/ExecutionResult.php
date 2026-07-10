<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

use Hmennen90\GraphQL\Engine\Error\GraphQLError;

/**
 * The outcome of executing an operation: the canonical {data, errors, extensions}
 * response envelope.
 *
 * A "request error" (parse/validation failure) produces a result with no data key
 * (use {@see ExecutionResult::withErrors()}); a "field error" produces partial data
 * alongside errors.
 */
final class ExecutionResult
{
    /**
     * @param  array<int, GraphQLError>  $errors
     * @param  array<string, mixed>  $extensions
     */
    public function __construct(
        public readonly mixed $data = null,
        public readonly array $errors = [],
        public readonly array $extensions = [],
        private readonly bool $hasData = true,
    ) {
    }

    /**
     * Build a result that carries only errors (no data key in the output),
     * as required for parse/validation request errors.
     *
     * @param  array<int, GraphQLError>  $errors
     * @param  array<string, mixed>  $extensions
     */
    public static function withErrors(array $errors, array $extensions = []): self
    {
        return new self(data: null, errors: $errors, extensions: $extensions, hasData: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->hasData) {
            $result['data'] = $this->data;
        }

        if ($this->errors !== []) {
            $result['errors'] = array_map(
                static fn (GraphQLError $error): array => $error->toArray(),
                $this->errors,
            );
        }

        if ($this->extensions !== []) {
            $result['extensions'] = $this->extensions;
        }

        return $result;
    }
}
