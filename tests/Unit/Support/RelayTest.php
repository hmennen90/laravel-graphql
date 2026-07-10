<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Support;

use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Support\Relay\Relay;
use PHPUnit\Framework\TestCase;

final class RelayTest extends TestCase
{
    public function test_connection_from_array_slices_and_reports_page_info(): void
    {
        $items = [['id' => '1'], ['id' => '2'], ['id' => '3'], ['id' => '4']];
        $connection = Relay::connectionFromArray($items, ['first' => 2]);

        $this->assertCount(2, $connection['edges']);
        $this->assertSame('1', $connection['edges'][0]['node']['id']);
        $this->assertTrue($connection['pageInfo']['hasNextPage']);
        $this->assertFalse($connection['pageInfo']['hasPreviousPage']);
        $this->assertSame(4, $connection['totalCount']);
    }

    public function test_after_cursor_paginates_forward(): void
    {
        $items = [['id' => '1'], ['id' => '2'], ['id' => '3']];
        $firstPage = Relay::connectionFromArray($items, ['first' => 1]);
        $afterCursor = $firstPage['edges'][0]['cursor'];

        $secondPage = Relay::connectionFromArray($items, ['first' => 1, 'after' => $afterCursor]);

        $this->assertSame('2', $secondPage['edges'][0]['node']['id']);
        $this->assertTrue($secondPage['pageInfo']['hasPreviousPage']);
    }

    public function test_connection_type_executes(): void
    {
        $item = new ObjectType('Item', [FieldDefinition::make('id', Type::nonNull(Type::id()))]);
        $connectionType = Relay::connectionType($item);

        $query = new ObjectType('Query', [
            FieldDefinition::make('items', $connectionType,
                args: [Argument::make('first', Type::int())],
                resolve: fn ($root, array $args): array => Relay::connectionFromArray(
                    [['id' => 'a'], ['id' => 'b'], ['id' => 'c']],
                    $args,
                )),
        ]);

        $schema = new Schema(new SchemaConfig(query: $query));
        $result = Executor::execute($schema, Parser::parse(
            '{ items(first: 2) { edges { node { id } cursor } pageInfo { hasNextPage endCursor } totalCount } }',
        ))->toArray();

        $items = $result['data']['items'];
        $this->assertSame(['a', 'b'], array_column(array_column($items['edges'], 'node'), 'id'));
        $this->assertTrue($items['pageInfo']['hasNextPage']);
        $this->assertSame(3, $items['totalCount']);
    }
}
