<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Conformance;

use Hmennen90\GraphQL\Engine\Error\SyntaxError;
use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Throwable;

/**
 * Property/fuzz tests for the hand-written lexer + parser.
 *
 * Two invariants:
 *  - Robustness: for ANY input, Parser::parse() returns a DocumentNode or throws a
 *    SyntaxError — never any other Throwable (TypeError, Error, unhandled recursion).
 *  - Soundness: structurally well-formed generated documents always parse.
 *
 * Generation is seeded (deterministic) so failures reproduce.
 */
final class ParserFuzzTest extends \PHPUnit\Framework\TestCase
{
    private const string ALPHABET = "abcABC_09 \n\t{}()[]:!@#\"'.,=$|&…\\/";

    public function test_never_throws_anything_but_syntax_error_on_random_noise(): void
    {
        mt_srand(1337);
        for ($i = 0; $i < 2000; $i++) {
            $source = $this->randomNoise(mt_rand(0, 60));
            $this->assertParsesOrSyntaxError($source);
        }
    }

    public function test_never_crashes_on_corrupted_valid_documents(): void
    {
        mt_srand(42);
        for ($i = 0; $i < 1000; $i++) {
            $source = $this->corrupt($this->randomDocument(0));
            $this->assertParsesOrSyntaxError($source);
        }
    }

    public function test_well_formed_generated_documents_parse(): void
    {
        mt_srand(7);
        for ($i = 0; $i < 1000; $i++) {
            $source = $this->randomDocument(0);
            try {
                $this->assertInstanceOf(DocumentNode::class, Parser::parse($source));
            } catch (Throwable $e) {
                $this->fail("Well-formed document failed to parse:\n{$source}\n\n{$e->getMessage()}");
            }
        }
    }

    private function assertParsesOrSyntaxError(string $source): void
    {
        try {
            Parser::parse($source);
        } catch (SyntaxError) {
            // acceptable
        } catch (Throwable $e) {
            $this->fail(sprintf(
                "Parser threw %s (expected SyntaxError) on input:\n%s\n\n%s",
                $e::class,
                var_export($source, true),
                $e->getMessage(),
            ));
        }

        $this->assertTrue(true);
    }

    private function randomNoise(int $length): string
    {
        $out = '';
        $len = strlen(self::ALPHABET);
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[mt_rand(0, $len - 1)];
        }

        return $out;
    }

    private function corrupt(string $source): string
    {
        $ops = mt_rand(1, 4);
        for ($i = 0; $i < $ops && $source !== ''; $i++) {
            $pos = mt_rand(0, strlen($source) - 1);
            $source = match (mt_rand(0, 2)) {
                0 => substr($source, 0, $pos),                                   // truncate
                1 => substr_replace($source, $this->randomNoise(3), $pos, 0),    // insert noise
                default => substr_replace($source, '', $pos, 1),                 // delete a char
            };
        }

        return $source;
    }

    private function randomDocument(int $depth): string
    {
        $op = ['query', 'mutation', ''][mt_rand(0, 2)];
        // A name (and thus the keyword) is only legal on a non-anonymous operation.
        $prefix = $op === '' ? '' : $op.(mt_rand(0, 1) === 1 ? ' '.$this->randomName() : '').' ';

        return $prefix.$this->randomSelectionSet($depth);
    }

    private function randomSelectionSet(int $depth): string
    {
        $count = mt_rand(1, 3);
        $fields = [];
        for ($i = 0; $i < $count; $i++) {
            $fields[] = $this->randomField($depth);
        }

        return '{ '.implode(' ', $fields).' }';
    }

    private function randomField(int $depth): string
    {
        $field = $this->randomName();
        if (mt_rand(0, 2) === 0) {
            $field = $this->randomName().': '.$field; // alias
        }
        if (mt_rand(0, 1) === 1) {
            $field .= '('.$this->randomName().': '.$this->randomValue().')';
        }
        if ($depth < 3 && mt_rand(0, 2) === 0) {
            $field .= ' '.$this->randomSelectionSet($depth + 1);
        }

        return $field;
    }

    private function randomValue(): string
    {
        return match (mt_rand(0, 6)) {
            0 => (string) mt_rand(-1000, 1000),
            1 => (string) (mt_rand(-1000, 1000) / 10),
            2 => '"'.$this->randomName().'"',
            3 => ['true', 'false', 'null'][mt_rand(0, 2)],
            4 => strtoupper($this->randomName()), // enum
            5 => '['.$this->randomValue().', '.$this->randomValue().']',
            default => '{'.$this->randomName().': '.$this->randomValue().'}',
        };
    }

    private function randomName(): string
    {
        $letters = 'abcdefghijklmnopqrstuvwxyz';
        $out = $letters[mt_rand(0, 25)];
        for ($i = 0, $n = mt_rand(0, 6); $i < $n; $i++) {
            $out .= $letters[mt_rand(0, 25)];
        }

        return $out;
    }
}
