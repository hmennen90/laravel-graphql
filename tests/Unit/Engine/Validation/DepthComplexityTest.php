<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Validation;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Validation\DocumentValidator;
use PHPUnit\Framework\TestCase;

final class DepthComplexityTest extends TestCase
{
    private function schema(): Schema
    {
        return SchemaBuilder::fromSdl(<<<'GRAPHQL'
            type Query { hello: String! me: User }
            type User { id: ID! name: String friend: User }
            GRAPHQL);
    }

    /**
     * @param  array<string, int>  $options
     * @return array<int, string>
     */
    private function validate(string $query, array $options): array
    {
        return array_map(
            static fn ($e): string => $e->getMessage(),
            DocumentValidator::validate($this->schema(), Parser::parse($query), $options),
        );
    }

    public function test_depth_limit_rejects_deep_queries(): void
    {
        $errors = $this->validate('{ me { friend { friend { id } } } }', ['maxDepth' => 2]);
        $this->assertStringContainsString('depth', strtolower(implode("\n", $errors)));
    }

    public function test_depth_limit_allows_shallow_queries(): void
    {
        $this->assertSame([], $this->validate('{ me { id } }', ['maxDepth' => 5]));
    }

    public function test_complexity_limit_rejects_wide_queries(): void
    {
        $errors = $this->validate('{ hello me { id name } }', ['maxComplexity' => 2]);
        $this->assertStringContainsString('complexity', strtolower(implode("\n", $errors)));
    }

    public function test_complexity_limit_allows_small_queries(): void
    {
        $this->assertSame([], $this->validate('{ hello }', ['maxComplexity' => 10]));
    }
}
