<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Error;

use Hmennen90\GraphQL\Engine\Error\GraphQLError;
use Hmennen90\GraphQL\Engine\Language\Source;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GraphQLErrorTest extends TestCase
{
    public function test_it_exposes_the_message(): void
    {
        $error = new GraphQLError('Something went wrong');

        $this->assertSame('Something went wrong', $error->getMessage());
        $this->assertSame([], $error->getPath());
        $this->assertSame([], $error->getLocations());
        $this->assertSame([], $error->getExtensions());
    }

    public function test_it_computes_locations_from_source_and_positions(): void
    {
        $source = new Source("query {\n  foo\n}");
        // position of "foo" (line 2, column 3)
        $position = strpos($source->body(), 'foo');

        $error = new GraphQLError('bad', source: $source, positions: [$position]);

        $this->assertSame([['line' => 2, 'column' => 3]], $error->getLocations());
    }

    public function test_it_carries_path_and_extensions_and_previous(): void
    {
        $previous = new RuntimeException('boom');
        $error = new GraphQLError(
            'nope',
            path: ['user', 0, 'name'],
            extensions: ['category' => 'authorization'],
            previous: $previous,
        );

        $this->assertSame(['user', 0, 'name'], $error->getPath());
        $this->assertSame(['category' => 'authorization'], $error->getExtensions());
        $this->assertSame($previous, $error->getPrevious());
    }

    public function test_to_array_emits_the_canonical_shape(): void
    {
        $source = new Source("{\n  foo\n}");
        $error = new GraphQLError(
            'bad field',
            source: $source,
            positions: [strpos($source->body(), 'foo')],
            path: ['foo'],
            extensions: ['code' => 'X'],
        );

        $this->assertSame([
            'message' => 'bad field',
            'locations' => [['line' => 2, 'column' => 3]],
            'path' => ['foo'],
            'extensions' => ['code' => 'X'],
        ], $error->toArray());
    }

    public function test_to_array_omits_empty_optional_keys(): void
    {
        $error = new GraphQLError('plain');

        $this->assertSame(['message' => 'plain'], $error->toArray());
    }
}
