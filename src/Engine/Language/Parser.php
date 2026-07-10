<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language;

use Hmennen90\GraphQL\Engine\Error\SyntaxError;
use Hmennen90\GraphQL\Engine\Language\AST\ArgumentNode;
use Hmennen90\GraphQL\Engine\Language\AST\BooleanValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\DefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
use Hmennen90\GraphQL\Engine\Language\AST\EnumTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\EnumValueDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\EnumValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\FieldDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\FloatValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentSpreadNode;
use Hmennen90\GraphQL\Engine\Language\AST\InlineFragmentNode;
use Hmennen90\GraphQL\Engine\Language\AST\InputObjectTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\InputObjectTypeExtensionNode;
use Hmennen90\GraphQL\Engine\Language\AST\InputValueDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\InterfaceTypeExtensionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectTypeExtensionNode;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\InterfaceTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\Location;
use Hmennen90\GraphQL\Engine\Language\AST\ListValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\NamedTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\Node;
use Hmennen90\GraphQL\Engine\Language\AST\NonNullTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\NullValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectFieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationType;
use Hmennen90\GraphQL\Engine\Language\AST\OperationTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ScalarTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\SchemaDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\SelectionNode;
use Hmennen90\GraphQL\Engine\Language\AST\SelectionSetNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\TypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\TypeSystemDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\UnionTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\VariableDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\VariableNode;

/**
 * A recursive-descent parser turning a {@see Source} into a {@see DocumentNode}.
 * Handles both executable operations and SDL (type-system) definitions.
 */
final class Parser
{
    private readonly Lexer $lexer;

    private function __construct(private readonly Source $source)
    {
        $this->lexer = new Lexer($source);
    }

    public static function parse(string|Source $source): DocumentNode
    {
        $source = $source instanceof Source ? $source : new Source($source);

        return (new self($source))->parseDocument();
    }

    private function parseDocument(): DocumentNode
    {
        $start = $this->expect(TokenKind::SOF);
        $definitions = [];

        do {
            $definitions[] = $this->parseDefinition();
        } while (! $this->peek(TokenKind::EOF));

        $this->expect(TokenKind::EOF);

        return $this->loc(new DocumentNode($definitions), $start);
    }

    private function parseDefinition(): DefinitionNode
    {
        if ($this->peek(TokenKind::BRACE_L)) {
            return $this->parseOperationDefinition();
        }

        if ($this->peekDescription()) {
            return $this->parseTypeSystemDefinition();
        }

        if ($this->peek(TokenKind::NAME)) {
            $keyword = $this->token()->value;

            return match ($keyword) {
                'query', 'mutation', 'subscription' => $this->parseOperationDefinition(),
                'fragment' => $this->parseFragmentDefinition(),
                'extend' => $this->parseTypeExtension(),
                'schema', 'scalar', 'type', 'interface', 'union', 'enum', 'input', 'directive'
                    => $this->parseTypeSystemDefinition(),
                default => throw $this->unexpected(),
            };
        }

        throw $this->unexpected();
    }

    // -- Operations -----------------------------------------------------------

    private function parseOperationDefinition(): OperationDefinitionNode
    {
        $start = $this->token();

        if ($this->peek(TokenKind::BRACE_L)) {
            return $this->loc(
                new OperationDefinitionNode(OperationType::QUERY, null, [], [], $this->parseSelectionSet()),
                $start,
            );
        }

        $operation = $this->parseOperationType();
        $name = $this->peek(TokenKind::NAME) ? $this->parseName() : null;

        return $this->loc(
            new OperationDefinitionNode(
                $operation,
                $name,
                $this->parseVariableDefinitions(),
                $this->parseDirectives(false),
                $this->parseSelectionSet(),
            ),
            $start,
        );
    }

    private function parseOperationType(): OperationType
    {
        $token = $this->expect(TokenKind::NAME);

        return match ($token->value) {
            'query' => OperationType::QUERY,
            'mutation' => OperationType::MUTATION,
            'subscription' => OperationType::SUBSCRIPTION,
            default => throw SyntaxError::at(
                $this->source,
                $token->start,
                sprintf('Expected operation type, found "%s".', (string) $token->value),
            ),
        };
    }

    /**
     * @return array<int, VariableDefinitionNode>
     */
    private function parseVariableDefinitions(): array
    {
        if (! $this->peek(TokenKind::PAREN_L)) {
            return [];
        }

        $this->expect(TokenKind::PAREN_L);
        $definitions = [];
        do {
            $definitions[] = $this->parseVariableDefinition();
        } while (! $this->peek(TokenKind::PAREN_R));
        $this->expect(TokenKind::PAREN_R);

        return $definitions;
    }

    private function parseVariableDefinition(): VariableDefinitionNode
    {
        $start = $this->token();
        $variable = $this->parseVariable();
        $this->expect(TokenKind::COLON);
        $type = $this->parseTypeReference();
        $default = $this->skip(TokenKind::EQUALS) ? $this->parseValueLiteral(true) : null;

        return $this->loc(
            new VariableDefinitionNode($variable, $type, $default, $this->parseDirectives(true)),
            $start,
        );
    }

    private function parseVariable(): VariableNode
    {
        $start = $this->expect(TokenKind::DOLLAR);

        return $this->loc(new VariableNode($this->parseName()), $start);
    }

    private function parseSelectionSet(): SelectionSetNode
    {
        $start = $this->expect(TokenKind::BRACE_L);
        $selections = [];
        do {
            $selections[] = $this->parseSelection();
        } while (! $this->peek(TokenKind::BRACE_R));
        $this->expect(TokenKind::BRACE_R);

        return $this->loc(new SelectionSetNode($selections), $start);
    }

    private function parseSelection(): SelectionNode
    {
        return $this->peek(TokenKind::SPREAD) ? $this->parseFragment() : $this->parseField();
    }

    private function parseField(): FieldNode
    {
        $start = $this->token();
        $nameOrAlias = $this->parseName();

        if ($this->skip(TokenKind::COLON)) {
            $alias = $nameOrAlias;
            $name = $this->parseName();
        } else {
            $alias = null;
            $name = $nameOrAlias;
        }

        return $this->loc(
            new FieldNode(
                $alias,
                $name,
                $this->parseArguments(false),
                $this->parseDirectives(false),
                $this->peek(TokenKind::BRACE_L) ? $this->parseSelectionSet() : null,
            ),
            $start,
        );
    }

    private function parseFragment(): SelectionNode
    {
        $start = $this->expect(TokenKind::SPREAD);

        if ($this->peek(TokenKind::NAME) && $this->token()->value !== 'on') {
            return $this->loc(
                new FragmentSpreadNode($this->parseName(), $this->parseDirectives(false)),
                $start,
            );
        }

        $typeCondition = null;
        if ($this->expectOptionalKeyword('on')) {
            $typeCondition = $this->parseNamedType();
        }

        return $this->loc(
            new InlineFragmentNode($typeCondition, $this->parseDirectives(false), $this->parseSelectionSet()),
            $start,
        );
    }

    private function parseFragmentDefinition(): FragmentDefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('fragment');
        $name = $this->parseName();
        $this->expectKeyword('on');

        return $this->loc(
            new FragmentDefinitionNode(
                $name,
                $this->parseNamedType(),
                $this->parseDirectives(false),
                $this->parseSelectionSet(),
            ),
            $start,
        );
    }

    /**
     * @return array<int, ArgumentNode>
     */
    private function parseArguments(bool $const): array
    {
        if (! $this->peek(TokenKind::PAREN_L)) {
            return [];
        }

        $this->expect(TokenKind::PAREN_L);
        $arguments = [];
        do {
            $start = $this->token();
            $name = $this->parseName();
            $this->expect(TokenKind::COLON);
            $arguments[] = $this->loc(new ArgumentNode($name, $this->parseValueLiteral($const)), $start);
        } while (! $this->peek(TokenKind::PAREN_R));
        $this->expect(TokenKind::PAREN_R);

        return $arguments;
    }

    /**
     * @return array<int, DirectiveNode>
     */
    private function parseDirectives(bool $const): array
    {
        $directives = [];
        while ($this->peek(TokenKind::AT)) {
            $start = $this->expect(TokenKind::AT);
            $directives[] = $this->loc(
                new DirectiveNode($this->parseName(), $this->parseArguments($const)),
                $start,
            );
        }

        return $directives;
    }

    // -- Values ---------------------------------------------------------------

    private function parseValueLiteral(bool $const): ValueNode
    {
        $token = $this->token();

        switch ($token->kind) {
            case TokenKind::BRACKET_L:
                return $this->parseListValue($const);
            case TokenKind::BRACE_L:
                return $this->parseObjectValue($const);
            case TokenKind::INT:
                $this->advance();

                return $this->loc(new IntValueNode((string) $token->value), $token);
            case TokenKind::FLOAT:
                $this->advance();

                return $this->loc(new FloatValueNode((string) $token->value), $token);
            case TokenKind::STRING:
            case TokenKind::BLOCK_STRING:
                $this->advance();

                return $this->loc(
                    new StringValueNode((string) $token->value, $token->kind === TokenKind::BLOCK_STRING),
                    $token,
                );
            case TokenKind::NAME:
                $this->advance();

                return $this->loc(match ($token->value) {
                    'true' => new BooleanValueNode(true),
                    'false' => new BooleanValueNode(false),
                    'null' => new NullValueNode(),
                    default => new EnumValueNode((string) $token->value),
                }, $token);
            case TokenKind::DOLLAR:
                if (! $const) {
                    return $this->parseVariable();
                }
                throw SyntaxError::at($this->source, $token->start, 'Unexpected variable "$" in constant value.');
            default:
                throw $this->unexpected();
        }
    }

    private function parseListValue(bool $const): ListValueNode
    {
        $start = $this->expect(TokenKind::BRACKET_L);
        $values = [];
        while (! $this->peek(TokenKind::BRACKET_R)) {
            $values[] = $this->parseValueLiteral($const);
        }
        $this->expect(TokenKind::BRACKET_R);

        return $this->loc(new ListValueNode($values), $start);
    }

    private function parseObjectValue(bool $const): ObjectValueNode
    {
        $start = $this->expect(TokenKind::BRACE_L);
        $fields = [];
        while (! $this->peek(TokenKind::BRACE_R)) {
            $fieldStart = $this->token();
            $name = $this->parseName();
            $this->expect(TokenKind::COLON);
            $fields[] = $this->loc(new ObjectFieldNode($name, $this->parseValueLiteral($const)), $fieldStart);
        }
        $this->expect(TokenKind::BRACE_R);

        return $this->loc(new ObjectValueNode($fields), $start);
    }

    // -- Type references ------------------------------------------------------

    private function parseTypeReference(): TypeNode
    {
        $start = $this->token();

        if ($this->skip(TokenKind::BRACKET_L)) {
            $inner = $this->parseTypeReference();
            $this->expect(TokenKind::BRACKET_R);
            $type = $this->loc(new ListTypeNode($inner), $start);
        } else {
            $type = $this->parseNamedType();
        }

        if ($this->skip(TokenKind::BANG)) {
            return $this->loc(new NonNullTypeNode($type), $start);
        }

        return $type;
    }

    private function parseNamedType(): NamedTypeNode
    {
        $start = $this->token();

        return $this->loc(new NamedTypeNode($this->parseName()), $start);
    }

    // -- Type system (SDL) ----------------------------------------------------

    private function parseTypeExtension(): DefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('extend');
        $keyword = $this->token()->value;

        return match ($keyword) {
            'type' => $this->parseObjectTypeExtension($start),
            'interface' => $this->parseInterfaceTypeExtension($start),
            'input' => $this->parseInputObjectTypeExtension($start),
            default => throw SyntaxError::at(
                $this->source,
                $this->token()->start,
                sprintf('Unsupported type extension "extend %s".', (string) $keyword),
            ),
        };
    }

    private function parseObjectTypeExtension(Token $start): ObjectTypeExtensionNode
    {
        $this->expectKeyword('type');
        $name = $this->parseName();

        return $this->loc(new ObjectTypeExtensionNode(
            $name,
            $this->parseImplementsInterfaces(),
            $this->parseDirectives(true),
            $this->parseFieldsDefinition(),
        ), $start);
    }

    private function parseInterfaceTypeExtension(Token $start): InterfaceTypeExtensionNode
    {
        $this->expectKeyword('interface');
        $name = $this->parseName();

        return $this->loc(new InterfaceTypeExtensionNode(
            $name,
            $this->parseImplementsInterfaces(),
            $this->parseDirectives(true),
            $this->parseFieldsDefinition(),
        ), $start);
    }

    private function parseInputObjectTypeExtension(Token $start): InputObjectTypeExtensionNode
    {
        $this->expectKeyword('input');
        $name = $this->parseName();
        $directives = $this->parseDirectives(true);

        $fields = [];
        if ($this->peek(TokenKind::BRACE_L)) {
            $this->expect(TokenKind::BRACE_L);
            while (! $this->peek(TokenKind::BRACE_R)) {
                $fields[] = $this->parseInputValueDefinition();
            }
            $this->expect(TokenKind::BRACE_R);
        }

        return $this->loc(new InputObjectTypeExtensionNode($name, $directives, $fields), $start);
    }

    private function parseTypeSystemDefinition(): TypeSystemDefinitionNode
    {
        $description = $this->parseDescription();
        $keyword = $this->token()->value;

        return match ($keyword) {
            'schema' => $this->parseSchemaDefinition($description),
            'scalar' => $this->parseScalarTypeDefinition($description),
            'type' => $this->parseObjectTypeDefinition($description),
            'interface' => $this->parseInterfaceTypeDefinition($description),
            'union' => $this->parseUnionTypeDefinition($description),
            'enum' => $this->parseEnumTypeDefinition($description),
            'input' => $this->parseInputObjectTypeDefinition($description),
            'directive' => $this->parseDirectiveDefinition($description),
            default => throw $this->unexpected(),
        };
    }

    private function parseDescription(): ?string
    {
        if ($this->peekDescription()) {
            $token = $this->token();
            $this->advance();

            return (string) $token->value;
        }

        return null;
    }

    private function parseSchemaDefinition(?string $description): SchemaDefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('schema');
        $directives = $this->parseDirectives(true);

        $this->expect(TokenKind::BRACE_L);
        $operationTypes = [];
        while (! $this->peek(TokenKind::BRACE_R)) {
            $opStart = $this->token();
            $operation = $this->parseOperationType();
            $this->expect(TokenKind::COLON);
            $operationTypes[] = $this->loc(
                new OperationTypeDefinitionNode($operation, $this->parseNamedType()),
                $opStart,
            );
        }
        $this->expect(TokenKind::BRACE_R);

        return $this->loc(new SchemaDefinitionNode($description, $directives, $operationTypes), $start);
    }

    private function parseScalarTypeDefinition(?string $description): ScalarTypeDefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('scalar');

        return $this->loc(
            new ScalarTypeDefinitionNode($description, $this->parseName(), $this->parseDirectives(true)),
            $start,
        );
    }

    private function parseObjectTypeDefinition(?string $description): ObjectTypeDefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('type');
        $name = $this->parseName();

        return $this->loc(
            new ObjectTypeDefinitionNode(
                $description,
                $name,
                $this->parseImplementsInterfaces(),
                $this->parseDirectives(true),
                $this->parseFieldsDefinition(),
            ),
            $start,
        );
    }

    private function parseInterfaceTypeDefinition(?string $description): InterfaceTypeDefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('interface');
        $name = $this->parseName();

        return $this->loc(
            new InterfaceTypeDefinitionNode(
                $description,
                $name,
                $this->parseImplementsInterfaces(),
                $this->parseDirectives(true),
                $this->parseFieldsDefinition(),
            ),
            $start,
        );
    }

    private function parseUnionTypeDefinition(?string $description): UnionTypeDefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('union');
        $name = $this->parseName();
        $directives = $this->parseDirectives(true);

        $types = [];
        if ($this->skip(TokenKind::EQUALS)) {
            $this->skip(TokenKind::PIPE);
            $types[] = $this->parseNamedType();
            while ($this->skip(TokenKind::PIPE)) {
                $types[] = $this->parseNamedType();
            }
        }

        return $this->loc(new UnionTypeDefinitionNode($description, $name, $directives, $types), $start);
    }

    private function parseEnumTypeDefinition(?string $description): EnumTypeDefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('enum');
        $name = $this->parseName();
        $directives = $this->parseDirectives(true);

        $values = [];
        if ($this->peek(TokenKind::BRACE_L)) {
            $this->expect(TokenKind::BRACE_L);
            while (! $this->peek(TokenKind::BRACE_R)) {
                $valueStart = $this->token();
                $valueDescription = $this->parseDescription();
                $values[] = $this->loc(
                    new EnumValueDefinitionNode($valueDescription, $this->parseName(), $this->parseDirectives(true)),
                    $valueStart,
                );
            }
            $this->expect(TokenKind::BRACE_R);
        }

        return $this->loc(new EnumTypeDefinitionNode($description, $name, $directives, $values), $start);
    }

    private function parseInputObjectTypeDefinition(?string $description): InputObjectTypeDefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('input');
        $name = $this->parseName();
        $directives = $this->parseDirectives(true);

        $fields = [];
        if ($this->peek(TokenKind::BRACE_L)) {
            $this->expect(TokenKind::BRACE_L);
            while (! $this->peek(TokenKind::BRACE_R)) {
                $fields[] = $this->parseInputValueDefinition();
            }
            $this->expect(TokenKind::BRACE_R);
        }

        return $this->loc(new InputObjectTypeDefinitionNode($description, $name, $directives, $fields), $start);
    }

    private function parseDirectiveDefinition(?string $description): DirectiveDefinitionNode
    {
        $start = $this->token();
        $this->expectKeyword('directive');
        $this->expect(TokenKind::AT);
        $name = $this->parseName();
        $arguments = $this->parseArgumentDefinitions();
        $repeatable = $this->expectOptionalKeyword('repeatable');
        $this->expectKeyword('on');

        $locations = [];
        $this->skip(TokenKind::PIPE);
        $locations[] = $this->parseName();
        while ($this->skip(TokenKind::PIPE)) {
            $locations[] = $this->parseName();
        }

        return $this->loc(
            new DirectiveDefinitionNode($description, $name, $arguments, $repeatable, $locations),
            $start,
        );
    }

    /**
     * @return array<int, NamedTypeNode>
     */
    private function parseImplementsInterfaces(): array
    {
        if (! $this->expectOptionalKeyword('implements')) {
            return [];
        }

        $this->skip(TokenKind::AMP);
        $interfaces = [$this->parseNamedType()];
        while ($this->skip(TokenKind::AMP)) {
            $interfaces[] = $this->parseNamedType();
        }

        return $interfaces;
    }

    /**
     * @return array<int, FieldDefinitionNode>
     */
    private function parseFieldsDefinition(): array
    {
        if (! $this->peek(TokenKind::BRACE_L)) {
            return [];
        }

        $this->expect(TokenKind::BRACE_L);
        $fields = [];
        while (! $this->peek(TokenKind::BRACE_R)) {
            $start = $this->token();
            $description = $this->parseDescription();
            $name = $this->parseName();
            $arguments = $this->parseArgumentDefinitions();
            $this->expect(TokenKind::COLON);
            $type = $this->parseTypeReference();
            $fields[] = $this->loc(
                new FieldDefinitionNode($description, $name, $arguments, $type, $this->parseDirectives(true)),
                $start,
            );
        }
        $this->expect(TokenKind::BRACE_R);

        return $fields;
    }

    /**
     * @return array<int, InputValueDefinitionNode>
     */
    private function parseArgumentDefinitions(): array
    {
        if (! $this->peek(TokenKind::PAREN_L)) {
            return [];
        }

        $this->expect(TokenKind::PAREN_L);
        $arguments = [];
        while (! $this->peek(TokenKind::PAREN_R)) {
            $arguments[] = $this->parseInputValueDefinition();
        }
        $this->expect(TokenKind::PAREN_R);

        return $arguments;
    }

    private function parseInputValueDefinition(): InputValueDefinitionNode
    {
        $start = $this->token();
        $description = $this->parseDescription();
        $name = $this->parseName();
        $this->expect(TokenKind::COLON);
        $type = $this->parseTypeReference();
        $default = $this->skip(TokenKind::EQUALS) ? $this->parseValueLiteral(true) : null;

        return $this->loc(
            new InputValueDefinitionNode($description, $name, $type, $default, $this->parseDirectives(true)),
            $start,
        );
    }

    // -- Token helpers --------------------------------------------------------

    private function token(): Token
    {
        return $this->lexer->token();
    }

    private function advance(): void
    {
        $this->lexer->advance();
    }

    private function peek(TokenKind $kind): bool
    {
        return $this->token()->kind === $kind;
    }

    private function peekDescription(): bool
    {
        return $this->peek(TokenKind::STRING) || $this->peek(TokenKind::BLOCK_STRING);
    }

    private function skip(TokenKind $kind): bool
    {
        if ($this->peek($kind)) {
            $this->advance();

            return true;
        }

        return false;
    }

    private function expect(TokenKind $kind): Token
    {
        $token = $this->token();
        if ($token->kind === $kind) {
            $this->advance();

            return $token;
        }

        throw SyntaxError::at(
            $this->source,
            $token->start,
            sprintf('Expected %s, found %s.', $kind->value, (string) $token),
        );
    }

    private function expectKeyword(string $value): void
    {
        $token = $this->token();
        if ($token->kind === TokenKind::NAME && $token->value === $value) {
            $this->advance();

            return;
        }

        throw SyntaxError::at(
            $this->source,
            $token->start,
            sprintf('Expected "%s", found %s.', $value, (string) $token),
        );
    }

    private function expectOptionalKeyword(string $value): bool
    {
        $token = $this->token();
        if ($token->kind === TokenKind::NAME && $token->value === $value) {
            $this->advance();

            return true;
        }

        return false;
    }

    private function parseName(): string
    {
        return (string) $this->expect(TokenKind::NAME)->value;
    }

    private function unexpected(): SyntaxError
    {
        $token = $this->token();

        return SyntaxError::at($this->source, $token->start, sprintf('Unexpected %s.', (string) $token));
    }

    /**
     * Attach a source location to a freshly built node.
     *
     * @template T of Node
     *
     * @param  T  $node
     * @return T
     */
    private function loc(Node $node, Token $start): Node
    {
        $node->loc = new Location($start->start, $this->token()->end, $this->source, $start, $this->token());

        return $node;
    }
}
