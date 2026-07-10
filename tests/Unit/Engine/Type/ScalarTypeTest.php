<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Type;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class ScalarTypeTest extends TestCase
{
    public function test_int_serializes_and_rejects_out_of_range(): void
    {
        $int = Type::int();

        $this->assertSame(42, $int->serialize(42));
        $this->assertSame(42, $int->serialize('42'));
        $this->assertSame(1, $int->serialize(true));

        $this->expectException(CoercionError::class);
        $int->serialize(2 ** 31);
    }

    public function test_int_parse_literal(): void
    {
        $this->assertSame(7, Type::int()->parseLiteral(new IntValueNode('7'), []));
    }

    public function test_float_serializes(): void
    {
        $this->assertSame(1.5, Type::float()->serialize(1.5));
        $this->assertSame(3.0, Type::float()->serialize(3));

        $this->expectException(CoercionError::class);
        Type::float()->serialize(INF);
    }

    public function test_string_serializes(): void
    {
        $this->assertSame('hi', Type::string()->serialize('hi'));
        $this->assertSame('3', Type::string()->serialize(3));

        $this->expectException(CoercionError::class);
        Type::string()->serialize(['array']);
    }

    public function test_boolean_serializes(): void
    {
        $this->assertTrue(Type::boolean()->serialize(1));
        $this->assertFalse(Type::boolean()->serialize(0));
    }

    public function test_id_accepts_int_and_string(): void
    {
        $this->assertSame('123', Type::id()->serialize(123));
        $this->assertSame('abc', Type::id()->serialize('abc'));
        $this->assertSame('7', Type::id()->parseLiteral(new IntValueNode('7'), []));
        $this->assertSame('x', Type::id()->parseLiteral(new StringValueNode('x'), []));
    }

    public function test_builtins_are_singletons(): void
    {
        $this->assertSame(Type::int(), Type::int());
        $this->assertSame('Int', Type::int()->name());
    }
}
