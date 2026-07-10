<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Language;

use Hmennen90\GraphQL\Engine\Language\Source;
use PHPUnit\Framework\TestCase;

final class SourceTest extends TestCase
{
    public function test_it_exposes_body_and_name(): void
    {
        $source = new Source('{ foo }', 'MyQuery');

        $this->assertSame('{ foo }', $source->body());
        $this->assertSame('MyQuery', $source->name());
    }

    public function test_it_defaults_the_name(): void
    {
        $this->assertSame('GraphQL', new Source('{}')->name());
    }

    public function test_it_maps_position_to_line_and_column(): void
    {
        $source = new Source("abc\ndef\nghi");

        $this->assertEquals(['line' => 1, 'column' => 1], (array) $source->getLocation(0));
        $this->assertEquals(['line' => 1, 'column' => 3], (array) $source->getLocation(2));
        // index 4 = 'd' on line 2
        $this->assertEquals(['line' => 2, 'column' => 1], (array) $source->getLocation(4));
        $this->assertEquals(['line' => 3, 'column' => 2], (array) $source->getLocation(9));
    }
}
