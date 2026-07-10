<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language;

use Hmennen90\GraphQL\Engine\Error\SyntaxError;

/**
 * Converts a {@see Source} into a stream of {@see Token}s on demand.
 *
 * Ignored tokens (whitespace, commas, comments, BOM) are skipped. Call
 * {@see advance()} to consume the next token; {@see token()} returns the last one.
 */
final class Lexer
{
    private readonly string $body;

    private readonly int $length;

    private int $position = 0;

    private int $line = 1;

    private int $lineStart = 0;

    private Token $lastToken;

    public function __construct(private readonly Source $source)
    {
        $this->body = $source->body();
        $this->length = strlen($this->body);
        $this->lastToken = new Token(TokenKind::SOF, 0, 0, 0, 0);
    }

    public function source(): Source
    {
        return $this->source;
    }

    public function token(): Token
    {
        return $this->lastToken;
    }

    public function advance(): Token
    {
        return $this->lastToken = $this->readToken();
    }

    private function readToken(): Token
    {
        $this->skipIgnored();

        if ($this->position >= $this->length) {
            return $this->make(TokenKind::EOF, $this->position, $this->position);
        }

        $start = $this->position;
        $char = $this->body[$start];

        $punctuator = match ($char) {
            '!' => TokenKind::BANG,
            '$' => TokenKind::DOLLAR,
            '&' => TokenKind::AMP,
            '(' => TokenKind::PAREN_L,
            ')' => TokenKind::PAREN_R,
            ':' => TokenKind::COLON,
            '=' => TokenKind::EQUALS,
            '@' => TokenKind::AT,
            '[' => TokenKind::BRACKET_L,
            ']' => TokenKind::BRACKET_R,
            '{' => TokenKind::BRACE_L,
            '}' => TokenKind::BRACE_R,
            '|' => TokenKind::PIPE,
            default => null,
        };

        if ($punctuator !== null) {
            $this->position++;

            return $this->make($punctuator, $start, $this->position);
        }

        if ($char === '.') {
            return $this->readSpread($start);
        }

        if ($char === '_' || ctype_alpha($char)) {
            return $this->readName($start);
        }

        if ($char === '-' || ctype_digit($char)) {
            return $this->readNumber($start);
        }

        if ($char === '"') {
            if (substr($this->body, $start, 3) === '"""') {
                return $this->readBlockString($start);
            }

            return $this->readString($start);
        }

        throw SyntaxError::at($this->source, $start, sprintf('Unexpected character "%s".', $char));
    }

    private function skipIgnored(): void
    {
        while ($this->position < $this->length) {
            $char = $this->body[$this->position];

            if ($char === "\n") {
                $this->position++;
                $this->newLine();
            } elseif ($char === "\r") {
                $this->position++;
                if ($this->position < $this->length && $this->body[$this->position] === "\n") {
                    $this->position++;
                }
                $this->newLine();
            } elseif ($char === ' ' || $char === "\t" || $char === ',') {
                $this->position++;
            } elseif ($char === "\u{FEFF}") {
                $this->position++;
            } elseif ($char === '#') {
                $this->position++;
                while (
                    $this->position < $this->length
                    && $this->body[$this->position] !== "\n"
                    && $this->body[$this->position] !== "\r"
                ) {
                    $this->position++;
                }
            } else {
                break;
            }
        }
    }

    private function newLine(): void
    {
        $this->line++;
        $this->lineStart = $this->position;
    }

    private function readSpread(int $start): Token
    {
        if (substr($this->body, $start, 3) === '...') {
            $this->position += 3;

            return $this->make(TokenKind::SPREAD, $start, $this->position);
        }

        throw SyntaxError::at($this->source, $start, 'Unexpected character ".". Expected "...".');
    }

    private function readName(int $start): Token
    {
        $this->position++;
        while ($this->position < $this->length) {
            $char = $this->body[$this->position];
            if ($char === '_' || ctype_alnum($char)) {
                $this->position++;
            } else {
                break;
            }
        }

        return $this->make(
            TokenKind::NAME,
            $start,
            $this->position,
            substr($this->body, $start, $this->position - $start),
        );
    }

    private function readNumber(int $start): Token
    {
        $isFloat = false;

        if ($this->peek() === '-') {
            $this->position++;
        }

        if ($this->peek() === '0') {
            $this->position++;
            if ($this->peek() !== null && ctype_digit($this->peek())) {
                throw SyntaxError::at($this->source, $this->position, 'Invalid number, unexpected digit after 0.');
            }
        } else {
            $this->readDigits($start);
        }

        if ($this->peek() === '.') {
            $isFloat = true;
            $this->position++;
            $this->readDigits($start);
        }

        $peek = $this->peek();
        if ($peek === 'e' || $peek === 'E') {
            $isFloat = true;
            $this->position++;
            if ($this->peek() === '+' || $this->peek() === '-') {
                $this->position++;
            }
            $this->readDigits($start);
        }

        return $this->make(
            $isFloat ? TokenKind::FLOAT : TokenKind::INT,
            $start,
            $this->position,
            substr($this->body, $start, $this->position - $start),
        );
    }

    private function readDigits(int $start): void
    {
        $char = $this->peek();
        if ($char === null || ! ctype_digit($char)) {
            throw SyntaxError::at(
                $this->source,
                $this->position,
                $char === null
                    ? 'Invalid number, expected digit but got: <EOF>.'
                    : sprintf('Invalid number, expected digit but got: "%s".', $char),
            );
        }

        while (($char = $this->peek()) !== null && ctype_digit($char)) {
            $this->position++;
        }
    }

    private function readString(int $start): Token
    {
        $this->position++; // opening quote
        $value = '';

        while ($this->position < $this->length) {
            $char = $this->body[$this->position];

            if ($char === '"') {
                $this->position++;

                return $this->make(TokenKind::STRING, $start, $this->position, $value);
            }

            if ($char === "\n" || $char === "\r") {
                break;
            }

            if ($char === '\\') {
                $value .= $this->readEscape();

                continue;
            }

            $value .= $char;
            $this->position++;
        }

        throw SyntaxError::at($this->source, $start, 'Unterminated string.');
    }

    private function readEscape(): string
    {
        $this->position++; // consume backslash
        if ($this->position >= $this->length) {
            throw SyntaxError::at($this->source, $this->position, 'Unterminated string.');
        }

        $escaped = $this->body[$this->position];
        $this->position++;

        return match ($escaped) {
            '"' => '"',
            '\\' => '\\',
            '/' => '/',
            'b' => "\x08",
            'f' => "\f",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'u' => $this->readUnicodeEscape(),
            default => throw SyntaxError::at(
                $this->source,
                $this->position - 1,
                sprintf('Invalid character escape sequence: \\%s.', $escaped),
            ),
        };
    }

    private function readUnicodeEscape(): string
    {
        $hex = substr($this->body, $this->position, 4);
        if (strlen($hex) !== 4 || ! ctype_xdigit($hex)) {
            throw SyntaxError::at(
                $this->source,
                $this->position,
                sprintf('Invalid Unicode escape sequence: \\u%s.', $hex),
            );
        }

        $this->position += 4;

        return mb_chr((int) hexdec($hex), 'UTF-8');
    }

    private function readBlockString(int $start): Token
    {
        $this->position += 3; // opening """
        $rawLines = [''];

        while ($this->position < $this->length) {
            $char = $this->body[$this->position];

            if (substr($this->body, $this->position, 3) === '"""') {
                $this->position += 3;

                return $this->make(
                    TokenKind::BLOCK_STRING,
                    $start,
                    $this->position,
                    self::dedentBlockString($rawLines),
                );
            }

            if ($char === '\\' && substr($this->body, $this->position, 4) === '\\"""') {
                $rawLines[count($rawLines) - 1] .= '"""';
                $this->position += 4;

                continue;
            }

            if ($char === "\n") {
                $rawLines[] = '';
                $this->position++;
                $this->newLine();

                continue;
            }

            if ($char === "\r") {
                $rawLines[] = '';
                $this->position++;
                if ($this->position < $this->length && $this->body[$this->position] === "\n") {
                    $this->position++;
                }
                $this->newLine();

                continue;
            }

            $rawLines[count($rawLines) - 1] .= $char;
            $this->position++;
        }

        throw SyntaxError::at($this->source, $start, 'Unterminated block string.');
    }

    /**
     * The GraphQL BlockStringValue algorithm: strip common indentation and
     * leading/trailing blank lines.
     *
     * @param  array<int, string>  $lines
     */
    private static function dedentBlockString(array $lines): string
    {
        $commonIndent = null;

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line, " \t"));
            if ($indent < strlen($line) && ($commonIndent === null || $indent < $commonIndent)) {
                $commonIndent = $indent;
            }
        }

        if ($commonIndent !== null) {
            foreach ($lines as $i => $line) {
                if ($i === 0) {
                    continue;
                }
                $lines[$i] = substr($line, $commonIndent);
            }
        }

        while ($lines !== [] && trim($lines[0], " \t") === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim($lines[count($lines) - 1], " \t") === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    private function peek(): ?string
    {
        return $this->position < $this->length ? $this->body[$this->position] : null;
    }

    private function make(TokenKind $kind, int $start, int $end, ?string $value = null): Token
    {
        return new Token($kind, $start, $end, $this->line, $start - $this->lineStart + 1, $value);
    }
}
