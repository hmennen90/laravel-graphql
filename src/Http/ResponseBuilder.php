<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http;

use Hmennen90\GraphQL\Engine\Executor\ExecutionResult;
use Hmennen90\GraphQL\Execution\ErrorHandler;

/** Turns an {@see ExecutionResult} into the JSON-serializable response array. */
final class ResponseBuilder
{
    public function __construct(private readonly ErrorHandler $errorHandler)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(ExecutionResult $result): array
    {
        $response = $result->toArray();

        if ($result->errors !== []) {
            $response['errors'] = array_map(
                fn ($error): array => $this->errorHandler->format($error),
                $result->errors,
            );
        }

        return $response;
    }
}
