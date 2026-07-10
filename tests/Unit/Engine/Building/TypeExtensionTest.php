<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Building;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use PHPUnit\Framework\TestCase;

final class TypeExtensionTest extends TestCase
{
    public function test_extend_type_adds_fields(): void
    {
        $schema = SchemaBuilder::fromSdl(
            <<<'GRAPHQL'
                type Query { hello: String! }
                extend type Query { extra: String! }
                GRAPHQL,
            resolvers: [
                'Query' => [
                    'hello' => fn (): string => 'world',
                    'extra' => fn (): string => 'more',
                ],
            ],
        );

        $query = $schema->getQueryType();
        $this->assertNotNull($query);
        $this->assertTrue($query->hasField('hello'));
        $this->assertTrue($query->hasField('extra'));

        $result = Executor::execute($schema, Parser::parse('{ hello extra }'))->toArray();
        $this->assertSame(['data' => ['hello' => 'world', 'extra' => 'more']], $result);
    }

    public function test_extend_before_definition_is_order_independent(): void
    {
        $schema = SchemaBuilder::fromSdl(
            <<<'GRAPHQL'
                extend type Query { extra: String }
                type Query { hello: String! }
                GRAPHQL,
        );

        $query = $schema->getQueryType();
        $this->assertNotNull($query);
        $this->assertTrue($query->hasField('extra'));
    }
}
