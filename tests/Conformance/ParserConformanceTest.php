<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Conformance;

use Hmennen90\GraphQL\Engine\Error\SyntaxError;
use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
use Hmennen90\GraphQL\Engine\Language\Parser;
use PHPUnit\Framework\TestCase;

/**
 * GraphQL spec conformance — the language ("Language" section): the lexer/parser
 * accepts well-formed documents (block strings, comments, literals, variables,
 * directives) and rejects malformed ones with a {@see SyntaxError}.
 */
final class ParserConformanceTest extends TestCase
{
    public function test_parses_a_rich_query(): void
    {
        $doc = Parser::parse(<<<'GRAPHQL'
        # a comment
        query Hero($episode: Episode = JEDI, $withFriends: Boolean!) {
          hero(episode: $episode) {
            name
            friends @include(if: $withFriends) {
              ...FriendFields
            }
            ... on Droid { primaryFunction }
          }
        }
        fragment FriendFields on Character { id name appearsIn }
        GRAPHQL);

        $this->assertInstanceOf(DocumentNode::class, $doc);
        $this->assertCount(2, $doc->definitions);
    }

    public function test_block_strings_and_literals(): void
    {
        $doc = Parser::parse(<<<'GRAPHQL'
        mutation {
          create(input: {
            title: "plain"
            body: """
            block
            string
            """
            tags: ["a", "b"]
            count: 3
            ratio: 1.5
            active: true
            nothing: null
          })
        }
        GRAPHQL);

        $this->assertInstanceOf(DocumentNode::class, $doc);
    }

    public function test_parses_sdl_type_system(): void
    {
        $doc = Parser::parse(<<<'GRAPHQL'
        "The root"
        type Query implements Node & Timestamped {
          field(arg: Int = 1): [String!]!
        }
        interface Node { id: ID! }
        enum E { A B }
        input In { x: Int }
        union U = Query
        scalar DateTime
        extend type Query { extra: Int }
        GRAPHQL);

        $this->assertGreaterThanOrEqual(7, count($doc->definitions));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function malformed(): array
    {
        return [
            'unclosed brace' => ['{ field'],
            'missing selection close' => ['{ a { b }'],
            'bad token' => ['{ field(arg: ) }'],
            'stray colon' => ['query { : }'],
            'unterminated string' => ['{ f(a: "oops) }'],
            'empty document' => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformed')]
    public function test_rejects_malformed_documents(string $source): void
    {
        $this->expectException(SyntaxError::class);
        Parser::parse($source);
    }
}
