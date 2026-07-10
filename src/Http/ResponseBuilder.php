<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http;

use Hmennen90\GraphQL\Engine\Executor\ExecutionResult;
use Hmennen90\GraphQL\Execution\ErrorHandler;

/** Turns an {@see ExecutionResult} into the JSON-serializable response array. */
final readonly class ResponseBuilder
{
    public function __construct(private ErrorHandler $errorHandler)
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
                $this->errorHandler->format(...),
                $result->errors,
            );
        }

        return $response;
    }
}
