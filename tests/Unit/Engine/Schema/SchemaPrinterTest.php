<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Schema;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Schema\SchemaPrinter;
use PHPUnit\Framework\TestCase;

final class SchemaPrinterTest extends TestCase
{
    public function test_it_prints_the_schema_as_sdl(): void
    {
        $schema = SchemaBuilder::fromSdl(<<<'GRAPHQL'
            type Query {
              user(id: ID!): User
            }
            type User implements Node {
              id: ID!
              name: String
              role: Role
            }
            interface Node { id: ID! }
            enum Role { ADMIN MEMBER }
            GRAPHQL);

        $sdl = SchemaPrinter::print($schema);

        $this->assertStringContainsString('type User implements Node {', $sdl);
        $this->assertStringContainsString('user(id: ID!): User', $sdl);
        $this->assertStringContainsString('interface Node {', $sdl);
        $this->assertStringContainsString('enum Role {', $sdl);
        $this->assertStringContainsString('  ADMIN', $sdl);
        // built-in scalars are not printed
        $this->assertStringNotContainsString('scalar String', $sdl);
    }
}
