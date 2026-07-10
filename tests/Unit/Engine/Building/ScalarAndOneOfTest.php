<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Building;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Type\Definition\CustomScalarType;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Validation\DocumentValidator;
use PHPUnit\Framework\TestCase;

final class ScalarAndOneOfTest extends TestCase
{
    public function test_specified_by_url_is_captured(): void
    {
        $schema = SchemaBuilder::fromSdl(<<<'GRAPHQL'
            type Query { now: DateTime }
            scalar DateTime @specifiedBy(url: "https://example.com/datetime")
            GRAPHQL);

        $scalar = $schema->getType('DateTime');
        $this->assertInstanceOf(CustomScalarType::class, $scalar);
        $this->assertSame('https://example.com/datetime', $scalar->specifiedByUrl());
    }

    public function test_one_of_input_flagged(): void
    {
        $schema = $this->oneOfSchema();
        $type = $schema->getType('Filter');
        $this->assertInstanceOf(InputObjectType::class, $type);
        $this->assertTrue($type->isOneOf());
    }

    public function test_one_of_rejects_multiple_fields(): void
    {
        $errors = DocumentValidator::validate(
            $this->oneOfSchema(),
            Parser::parse('{ search(f: { byId: "1", byName: "x" }) }'),
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('exactly one', implode("\n", array_map(static fn ($e): string => $e->getMessage(), $errors)));
    }

    public function test_one_of_accepts_single_field(): void
    {
        $errors = DocumentValidator::validate(
            $this->oneOfSchema(),
            Parser::parse('{ search(f: { byId: "1" }) }'),
        );

        $this->assertSame([], $errors);
    }

    private function oneOfSchema(): \Hmennen90\GraphQL\Engine\Schema\Schema
    {
        return SchemaBuilder::fromSdl(<<<'GRAPHQL'
            type Query { search(f: Filter!): String }
            input Filter @oneOf { byId: ID byName: String }
            GRAPHQL);
    }
}
