<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Language;

use Hmennen90\GraphQL\Engine\Error\SyntaxError;
use Hmennen90\GraphQL\Engine\Language\Lexer;
use Hmennen90\GraphQL\Engine\Language\Source;
use Hmennen90\GraphQL\Engine\Language\TokenKind;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    private function lex(string $body): Lexer
    {
        return new Lexer(new Source($body));
    }

    public function test_it_starts_with_sof_and_ends_with_eof(): void
    {
        $lexer = $this->lex('');

        $this->assertSame(TokenKind::SOF, $lexer->token()->kind);
        $this->assertSame(TokenKind::EOF, $lexer->advance()->kind);
        // advancing past EOF stays at EOF
        $this->assertSame(TokenKind::EOF, $lexer->advance()->kind);
    }

    public function test_it_lexes_punctuators(): void
    {
        $lexer = $this->lex('! $ ( ) ... : = @ [ ] { } | &');
        $expected = [
            TokenKind::BANG, TokenKind::DOLLAR, TokenKind::PAREN_L, TokenKind::PAREN_R,
            TokenKind::SPREAD, TokenKind::COLON, TokenKind::EQUALS, TokenKind::AT,
            TokenKind::BRACKET_L, TokenKind::BRACKET_R, TokenKind::BRACE_L,
            TokenKind::BRACE_R, TokenKind::PIPE, TokenKind::AMP,
        ];

        foreach ($expected as $kind) {
            $this->assertSame($kind, $lexer->advance()->kind);
        }
        $this->assertSame(TokenKind::EOF, $lexer->advance()->kind);
    }

    public function test_it_lexes_names(): void
    {
        $token = $this->lex('  fooBar_123  ')->advance();

        $this->assertSame(TokenKind::NAME, $token->kind);
        $this->assertSame('fooBar_123', $token->value);
    }

    public function test_it_lexes_integers(): void
    {
        $token = $this->lex('-0 4 42 -123')->advance();
        $this->assertSame(TokenKind::INT, $token->kind);
        $this->assertSame('-0', $token->value);
    }

    public function test_it_lexes_floats(): void
    {
        foreach (['4.123', '-4.0', '0.0', '123e4', '123E4', '123e-4', '-1.23e+45', '4.0e2'] as $literal) {
            $token = $this->lex($literal)->advance();
            $this->assertSame(TokenKind::FLOAT, $token->kind, "Expected FLOAT for {$literal}");
            $this->assertSame($literal, $token->value);
        }
    }

    public function test_it_lexes_strings_with_escapes(): void
    {
        $token = $this->lex('"simple"')->advance();
        $this->assertSame(TokenKind::STRING, $token->kind);
        $this->assertSame('simple', $token->value);

        $this->assertSame("quote \" here", $this->lex('"quote \\" here"')->advance()->value);
        $this->assertSame("new\nline", $this->lex('"new\\nline"')->advance()->value);
        $this->assertSame("tab\ttab", $this->lex('"tab\\ttab"')->advance()->value);
        $this->assertSame('/', $this->lex('"\\/"')->advance()->value);
        $this->assertSame('A', $this->lex('"\\u0041"')->advance()->value);
    }

    public function test_it_lexes_block_strings_and_dedents(): void
    {
        $body = "\"\"\"\n    Hello,\n      World!\n\n    Yours\n    \"\"\"";
        $token = $this->lex($body)->advance();

        $this->assertSame(TokenKind::BLOCK_STRING, $token->kind);
        $this->assertSame("Hello,\n  World!\n\nYours", $token->value);
    }

    public function test_it_skips_commas_and_comments(): void
    {
        $lexer = $this->lex("a, # this is ignored\n b");

        $this->assertSame('a', $lexer->advance()->value);
        $this->assertSame('b', $lexer->advance()->value);
        $this->assertSame(TokenKind::EOF, $lexer->advance()->kind);
    }

    public function test_it_tracks_line_and_column(): void
    {
        $lexer = $this->lex("foo\n  bar");
        $lexer->advance(); // foo
        $bar = $lexer->advance();

        $this->assertSame('bar', $bar->value);
        $this->assertSame(2, $bar->line);
        $this->assertSame(3, $bar->column);
    }

    public function test_it_throws_on_unterminated_string(): void
    {
        $this->expectException(SyntaxError::class);
        $this->lex('"no end')->advance();
    }

    public function test_it_throws_on_unexpected_character(): void
    {
        $this->expectException(SyntaxError::class);
        $this->lex('?')->advance();
    }

    public function test_it_throws_on_incomplete_spread(): void
    {
        $this->expectException(SyntaxError::class);
        $this->lex('..')->advance();
    }
}
