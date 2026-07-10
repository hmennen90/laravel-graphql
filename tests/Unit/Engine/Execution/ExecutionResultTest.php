<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Execution;

use Hmennen90\GraphQL\Engine\Error\GraphQLError;
use Hmennen90\GraphQL\Engine\Executor\ExecutionResult;
use PHPUnit\Framework\TestCase;

final class ExecutionResultTest extends TestCase
{
    public function test_data_only_result(): void
    {
        $result = new ExecutionResult(['hello' => 'world']);

        $this->assertSame(['data' => ['hello' => 'world']], $result->toArray());
        $this->assertSame([], $result->errors);
    }

    public function test_partial_data_with_errors(): void
    {
        $result = new ExecutionResult(
            data: ['user' => null],
            errors: [new GraphQLError('boom', path: ['user'])],
        );

        $this->assertSame([
            'data' => ['user' => null],
            'errors' => [
                ['message' => 'boom', 'path' => ['user']],
            ],
        ], $result->toArray());
    }

    public function test_request_error_result_has_no_data_key(): void
    {
        $result = ExecutionResult::withErrors([new GraphQLError('Syntax Error')]);

        $this->assertSame([
            'errors' => [
                ['message' => 'Syntax Error'],
            ],
        ], $result->toArray());
    }

    public function test_extensions_are_appended(): void
    {
        $result = new ExecutionResult(['x' => 1], extensions: ['tracing' => ['ms' => 3]]);

        $this->assertSame([
            'data' => ['x' => 1],
            'extensions' => ['tracing' => ['ms' => 3]],
        ], $result->toArray());
    }
}
