<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\CodeFirst;

use Closure;
use Hmennen90\GraphQL\Engine\Language\Lexer;
use Hmennen90\GraphQL\Engine\Language\Source;
use Hmennen90\GraphQL\Engine\Language\TokenKind;
use Hmennen90\GraphQL\Engine\Type\Definition\OutputType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use RuntimeException;

/**
 * Parses a GraphQL type expression (e.g. `[User!]!`) into a {@see Type},
 * resolving named types through the provided resolver.
 */
final class TypeExpression
{
    /**
     * @param  Closure(string): (Type&OutputType)  $resolveNamed
     */
    public static function parse(string $expression, Closure $resolveNamed): Type&OutputType
    {
        $lexer = new Lexer(new Source($expression));
        $lexer->advance();

        $type = self::parseReference($lexer, $resolveNamed);

        if ($lexer->token()->kind !== TokenKind::EOF) {
            throw new RuntimeException(sprintf('Invalid type expression: "%s".', $expression));
        }

        return $type;
    }

    /**
     * @param  Closure(string): (Type&OutputType)  $resolveNamed
     */
    private static function parseReference(Lexer $lexer, Closure $resolveNamed): Type&OutputType
    {
        if ($lexer->token()->kind === TokenKind::BRACKET_L) {
            $lexer->advance();
            $inner = self::parseReference($lexer, $resolveNamed);
            if ($lexer->token()->kind !== TokenKind::BRACKET_R) {
                throw new RuntimeException('Expected "]" in type expression.');
            }
            $lexer->advance();
            $type = Type::listOf($inner);
        } else {
            $token = $lexer->token();
            if ($token->kind !== TokenKind::NAME || $token->value === null) {
                throw new RuntimeException('Expected a type name in type expression.');
            }
            $lexer->advance();
            $type = $resolveNamed($token->value);
        }

        if ($lexer->token()->kind === TokenKind::BANG) {
            $lexer->advance();

            return Type::nonNull($type);
        }

        return $type;
    }
}
