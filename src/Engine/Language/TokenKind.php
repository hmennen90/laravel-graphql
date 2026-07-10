<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language;

/**
 * The kinds of lexical tokens produced by the {@see Lexer}.
 */
enum TokenKind: string
{
    case SOF = '<SOF>';
    case EOF = '<EOF>';
    case BANG = '!';
    case DOLLAR = '$';
    case AMP = '&';
    case PAREN_L = '(';
    case PAREN_R = ')';
    case SPREAD = '...';
    case COLON = ':';
    case EQUALS = '=';
    case AT = '@';
    case BRACKET_L = '[';
    case BRACKET_R = ']';
    case BRACE_L = '{';
    case BRACE_R = '}';
    case PIPE = '|';
    case NAME = 'Name';
    case INT = 'Int';
    case FLOAT = 'Float';
    case STRING = 'String';
    case BLOCK_STRING = 'BlockString';
}
