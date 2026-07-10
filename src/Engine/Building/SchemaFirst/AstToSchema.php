<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\SchemaFirst;

use Hmennen90\GraphQL\Engine\Language\AST\BooleanValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
use Hmennen90\GraphQL\Engine\Language\AST\EnumTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\EnumValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\FloatValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\InputObjectTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\InterfaceTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\NamedTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\NonNullTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationType;
use Hmennen90\GraphQL\Engine\Language\AST\ScalarTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\SchemaDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\TypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\UnionTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\CustomScalarType;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumType;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumValueDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectField;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\InputType;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\OutputType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Type\Definition\UnionType;
use LogicException;

/**
 * Turns a parsed SDL {@see DocumentNode} into the same internal {@see Schema}
 * the code-first builder produces. Type references resolve lazily through a
 * shared registry so forward/cyclic references work regardless of definition order.
 */
final class AstToSchema
{
    /** @var array<string, Type&NamedType> */
    private array $types = [];

    /** @var array<string, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|UnionTypeDefinitionNode|EnumTypeDefinitionNode|InputObjectTypeDefinitionNode|ScalarTypeDefinitionNode> */
    private array $definitions = [];

    private ?SchemaDefinitionNode $schemaDefinition = null;

    public function __construct(
        private readonly DocumentNode $document,
        private readonly ResolverMap $resolvers,
    ) {
    }

    public function build(): Schema
    {
        foreach (Type::builtInScalars() as $name => $scalar) {
            $this->types[$name] = $scalar;
        }

        foreach ($this->document->definitions as $definition) {
            if ($definition instanceof SchemaDefinitionNode) {
                $this->schemaDefinition = $definition;
            } elseif (
                $definition instanceof ObjectTypeDefinitionNode
                || $definition instanceof InterfaceTypeDefinitionNode
                || $definition instanceof UnionTypeDefinitionNode
                || $definition instanceof EnumTypeDefinitionNode
                || $definition instanceof InputObjectTypeDefinitionNode
                || $definition instanceof ScalarTypeDefinitionNode
            ) {
                $this->definitions[$definition->name] = $definition;
            }
        }

        foreach ($this->definitions as $name => $definition) {
            $this->types[$name] = $this->createType($definition);
        }

        return new Schema(new SchemaConfig(
            query: $this->rootType(OperationType::QUERY, 'Query'),
            mutation: $this->rootType(OperationType::MUTATION, 'Mutation'),
            subscription: $this->rootType(OperationType::SUBSCRIPTION, 'Subscription'),
            types: array_values($this->types),
        ));
    }

    private function createType(
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|UnionTypeDefinitionNode|EnumTypeDefinitionNode|InputObjectTypeDefinitionNode|ScalarTypeDefinitionNode $definition,
    ): Type&NamedType {
        return match (true) {
            $definition instanceof ObjectTypeDefinitionNode => $this->createObjectType($definition),
            $definition instanceof InterfaceTypeDefinitionNode => $this->createInterfaceType($definition),
            $definition instanceof UnionTypeDefinitionNode => $this->createUnionType($definition),
            $definition instanceof EnumTypeDefinitionNode => $this->createEnumType($definition),
            $definition instanceof InputObjectTypeDefinitionNode => $this->createInputObjectType($definition),
            $definition instanceof ScalarTypeDefinitionNode => new CustomScalarType($definition->name, description: $definition->description),
        };
    }

    private function createObjectType(ObjectTypeDefinitionNode $node): ObjectType
    {
        return new ObjectType(
            $node->name,
            fn (): array => $this->buildFields($node->name, $node->fields),
            fn (): array => $this->buildInterfaceRefs($node->interfaces),
            $node->description,
        );
    }

    private function createInterfaceType(InterfaceTypeDefinitionNode $node): InterfaceType
    {
        $typeResolver = $this->resolvers->typeResolver($node->name);

        return new InterfaceType(
            $node->name,
            fn (): array => $this->buildFields($node->name, $node->fields),
            fn (): array => $this->buildInterfaceRefs($node->interfaces),
            $node->description,
            $typeResolver,
        );
    }

    private function createUnionType(UnionTypeDefinitionNode $node): UnionType
    {
        return new UnionType(
            $node->name,
            function () use ($node): array {
                $members = [];
                foreach ($node->types as $member) {
                    $type = $this->namedType($member->name);
                    if ($type instanceof ObjectType) {
                        $members[] = $type;
                    }
                }

                return $members;
            },
            $node->description,
            $this->resolvers->typeResolver($node->name),
        );
    }

    private function createEnumType(EnumTypeDefinitionNode $node): EnumType
    {
        $values = [];
        foreach ($node->values as $value) {
            $values[] = new EnumValueDefinition($value->name, description: $value->description);
        }

        return new EnumType($node->name, $values, $node->description);
    }

    private function createInputObjectType(InputObjectTypeDefinitionNode $node): InputObjectType
    {
        return new InputObjectType(
            $node->name,
            function () use ($node): array {
                $fields = [];
                foreach ($node->fields as $field) {
                    $type = $this->buildInputType($field->type);
                    $fields[] = $field->defaultValue !== null
                        ? new InputObjectField($field->name, $type, true, $this->literalToPhp($field->defaultValue), $field->description)
                        : new InputObjectField($field->name, $type, false, null, $field->description);
                }

                return $fields;
            },
            $node->description,
        );
    }

    /**
     * @param  array<int, \Hmennen90\GraphQL\Engine\Language\AST\FieldDefinitionNode>  $fieldNodes
     * @return array<int, FieldDefinition>
     */
    private function buildFields(string $typeName, array $fieldNodes): array
    {
        $fields = [];
        foreach ($fieldNodes as $fieldNode) {
            $args = [];
            foreach ($fieldNode->arguments as $argNode) {
                $argType = $this->buildInputType($argNode->type);
                $args[] = $argNode->defaultValue !== null
                    ? Argument::withDefault($argNode->name, $argType, $this->literalToPhp($argNode->defaultValue), $argNode->description)
                    : Argument::make($argNode->name, $argType, $argNode->description);
            }

            $fields[] = FieldDefinition::make(
                $fieldNode->name,
                $this->buildOutputType($fieldNode->type),
                $this->resolvers->resolver($typeName, $fieldNode->name),
                $args,
                $fieldNode->description,
            );
        }

        return $fields;
    }

    /**
     * @param  array<int, NamedTypeNode>  $interfaceNodes
     * @return array<int, InterfaceType>
     */
    private function buildInterfaceRefs(array $interfaceNodes): array
    {
        $interfaces = [];
        foreach ($interfaceNodes as $node) {
            $type = $this->namedType($node->name);
            if ($type instanceof InterfaceType) {
                $interfaces[] = $type;
            }
        }

        return $interfaces;
    }

    private function buildOutputType(TypeNode $node): Type&OutputType
    {
        if ($node instanceof NonNullTypeNode) {
            return Type::nonNull($this->buildOutputType($node->type));
        }
        if ($node instanceof ListTypeNode) {
            return Type::listOf($this->buildOutputType($node->type));
        }
        if ($node instanceof NamedTypeNode) {
            $type = $this->namedType($node->name);
            if (! $type instanceof OutputType) {
                throw new LogicException(sprintf('Type "%s" is not a valid output type.', $node->name));
            }

            return $type;
        }

        throw new LogicException('Unsupported type node.');
    }

    private function buildInputType(TypeNode $node): Type&InputType
    {
        if ($node instanceof NonNullTypeNode) {
            return Type::nonNull($this->buildInputType($node->type));
        }
        if ($node instanceof ListTypeNode) {
            return Type::listOf($this->buildInputType($node->type));
        }
        if ($node instanceof NamedTypeNode) {
            $type = $this->namedType($node->name);
            if (! $type instanceof InputType) {
                throw new LogicException(sprintf('Type "%s" is not a valid input type.', $node->name));
            }

            return $type;
        }

        throw new LogicException('Unsupported type node.');
    }

    private function namedType(string $name): Type&NamedType
    {
        if (! isset($this->types[$name])) {
            throw new LogicException(sprintf('Unknown type "%s" referenced in schema.', $name));
        }

        return $this->types[$name];
    }

    private function rootType(OperationType $operation, string $default): ?ObjectType
    {
        $name = $default;

        if ($this->schemaDefinition !== null) {
            $name = null;
            foreach ($this->schemaDefinition->operationTypes as $operationType) {
                if ($operationType->operation === $operation) {
                    $name = $operationType->type->name;
                }
            }
            if ($name === null) {
                return null;
            }
        }

        $type = $this->types[$name] ?? null;

        return $type instanceof ObjectType ? $type : null;
    }

    private function literalToPhp(ValueNode $node): mixed
    {
        if ($node instanceof ListValueNode) {
            return array_map(fn (ValueNode $v): mixed => $this->literalToPhp($v), $node->values);
        }

        if ($node instanceof ObjectValueNode) {
            $object = [];
            foreach ($node->fields as $field) {
                $object[$field->name] = $this->literalToPhp($field->value);
            }

            return $object;
        }

        return match (true) {
            $node instanceof IntValueNode => (int) $node->value,
            $node instanceof FloatValueNode => (float) $node->value,
            $node instanceof StringValueNode => $node->value,
            $node instanceof BooleanValueNode => $node->value,
            $node instanceof EnumValueNode => $node->value,
            default => null,
        };
    }
}
