<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Validation;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Error\GraphQLError;
use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentSpreadNode;
use Hmennen90\GraphQL\Engine\Language\AST\InlineFragmentNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\Node;
use Hmennen90\GraphQL\Engine\Language\AST\NullValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationType;
use Hmennen90\GraphQL\Engine\Language\AST\SelectionSetNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\VariableNode;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Type\Definition\CompositeType;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\InputType;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\LeafType;
use Hmennen90\GraphQL\Engine\Type\Definition\ListOfType;
use Hmennen90\GraphQL\Engine\Type\Definition\NonNull;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Type\Definition\UnionType;

/**
 * Validates an executable document against a schema, collecting all errors.
 * Implements the core GraphQL validation rules (field existence, arguments,
 * leaf/composite selections, variable usage, fragment validity, single-field
 * subscriptions) in a small set of passes.
 */
final class DocumentValidator
{
    /** @var array<int, GraphQLError> */
    private array $errors = [];

    /** @var array<string, FragmentDefinitionNode> */
    private array $fragments = [];

    private function __construct(private readonly Schema $schema, private readonly DocumentNode $document)
    {
        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $this->fragments[$definition->name] = $definition;
            }
        }
    }

    /**
     * @return array<int, GraphQLError>
     */
    public static function validate(Schema $schema, DocumentNode $document): array
    {
        $validator = new self($schema, $document);
        $validator->run();

        return $validator->errors;
    }

    private function run(): void
    {
        foreach ($this->document->definitions as $definition) {
            if ($definition instanceof OperationDefinitionNode) {
                $this->validateOperation($definition);
            }
        }

        $this->checkFragmentUsage();
        $this->checkFragmentCycles();
    }

    private function validateOperation(OperationDefinitionNode $operation): void
    {
        $rootType = match ($operation->operation) {
            OperationType::QUERY => $this->schema->getQueryType(),
            OperationType::MUTATION => $this->schema->getMutationType(),
            OperationType::SUBSCRIPTION => $this->schema->getSubscriptionType(),
        };

        if ($rootType === null) {
            $this->error(sprintf('Schema is not configured for %ss.', $operation->operation->value), $operation);

            return;
        }

        if ($operation->operation === OperationType::SUBSCRIPTION
            && count($operation->selectionSet->selections) !== 1) {
            $this->error('Subscription operations must select only a single top-level field.', $operation);
        }

        $this->checkSelectionSet($rootType, $operation->selectionSet, []);
        $this->checkVariableUsage($operation);
    }

    /**
     * @param  array<string, true>  $visitedFragments
     */
    private function checkSelectionSet(CompositeType $parentType, SelectionSetNode $selectionSet, array $visitedFragments): void
    {
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                $this->checkField($parentType, $selection, $visitedFragments);
            } elseif ($selection instanceof InlineFragmentNode) {
                $type = $parentType;
                if ($selection->typeCondition !== null) {
                    $resolved = $this->compositeType($selection->typeCondition->name);
                    if ($resolved === null) {
                        $this->error(sprintf('Unknown type "%s".', $selection->typeCondition->name), $selection);

                        continue;
                    }
                    $type = $resolved;
                }
                $this->checkSelectionSet($type, $selection->selectionSet, $visitedFragments);
            } elseif ($selection instanceof FragmentSpreadNode) {
                $this->checkFragmentSpread($selection, $visitedFragments);
            }
        }
    }

    /**
     * @param  array<string, true>  $visitedFragments
     */
    private function checkField(CompositeType $parentType, FieldNode $field, array $visitedFragments): void
    {
        if ($field->name === '__typename') {
            if ($field->selectionSet !== null) {
                $this->error('Field "__typename" must not have a selection since type "String" has no subfields.', $field);
            }

            return;
        }

        // Introspection root fields are handled by the executor.
        if (($field->name === '__schema' || $field->name === '__type')
            && $parentType === $this->schema->getQueryType()) {
            return;
        }

        if ($parentType instanceof UnionType) {
            $this->error(sprintf(
                'Cannot query field "%s" directly on union type "%s"; use a fragment.',
                $field->name,
                $parentType->name(),
            ), $field);

            return;
        }

        if (! ($parentType instanceof ObjectType || $parentType instanceof InterfaceType)
            || ! $parentType->hasField($field->name)) {
            $this->error(sprintf(
                'Cannot query field "%s" on type "%s".',
                $field->name,
                $parentType->name(),
            ), $field);

            return;
        }

        $fieldDef = $parentType->getField($field->name);
        $this->checkArguments($fieldDef, $field, $parentType->name());

        $namedType = Type::getNamedType($fieldDef->getType());
        $isLeaf = $namedType instanceof LeafType;

        if ($isLeaf && $field->selectionSet !== null) {
            $this->error(sprintf(
                'Field "%s" must not have a selection since type "%s" has no subfields.',
                $field->name,
                $namedType->name(),
            ), $field);
        }

        if (! $isLeaf && $field->selectionSet === null) {
            $this->error(sprintf(
                'Field "%s" of type "%s" requires a selection of subfields.',
                $field->name,
                (string) $fieldDef->getType(),
            ), $field);
        }

        if ($field->selectionSet !== null && $namedType instanceof CompositeType) {
            $this->checkSelectionSet($namedType, $field->selectionSet, $visitedFragments);
        }
    }

    private function checkArguments(FieldDefinition $fieldDef, FieldNode $field, string $typeName): void
    {
        $provided = [];
        foreach ($field->arguments as $argument) {
            $provided[$argument->name] = $argument->value;
            $argDef = $fieldDef->getArg($argument->name);
            if ($argDef === null) {
                $this->error(sprintf(
                    'Unknown argument "%s" on field "%s.%s".',
                    $argument->name,
                    $typeName,
                    $field->name,
                ), $argument);

                continue;
            }

            $this->checkValue($argDef->getType(), $argument->value, sprintf('Argument "%s"', $argument->name), $argument);
        }

        foreach ($fieldDef->args() as $argDef) {
            if ($argDef->getType() instanceof NonNull
                && ! $argDef->hasDefaultValue()
                && ! isset($provided[$argDef->getName()])) {
                $this->error(sprintf(
                    'Field "%s" argument "%s" of type "%s" is required but not provided.',
                    $field->name,
                    $argDef->getName(),
                    (string) $argDef->getType(),
                ), $field);
            }
        }
    }

    private function checkValue(Type&InputType $type, ValueNode $node, string $context, Node $blame): void
    {
        if ($node instanceof VariableNode) {
            return;
        }

        if ($type instanceof NonNull) {
            if ($node instanceof NullValueNode) {
                $this->error(sprintf('%s of type "%s" must not be null.', $context, (string) $type), $blame);

                return;
            }
            $inner = $type->wrappedType();
            if ($inner instanceof InputType) {
                $this->checkValue($inner, $node, $context, $blame);
            }

            return;
        }

        if ($node instanceof NullValueNode) {
            return;
        }

        if ($type instanceof ListOfType) {
            $inner = $type->wrappedType();
            if (! $inner instanceof InputType) {
                return;
            }
            if ($node instanceof ListValueNode) {
                foreach ($node->values as $item) {
                    $this->checkValue($inner, $item, $context, $blame);
                }
            } else {
                $this->checkValue($inner, $node, $context, $blame);
            }

            return;
        }

        if ($type instanceof InputObjectType) {
            $this->checkInputObject($type, $node, $context, $blame);

            return;
        }

        if ($type instanceof LeafType) {
            try {
                $type->parseLiteral($node, []);
            } catch (CoercionError $e) {
                $this->error(sprintf('%s has invalid value: %s', $context, $e->getMessage()), $blame);
            }
        }
    }

    private function checkInputObject(InputObjectType $type, ValueNode $node, string $context, Node $blame): void
    {
        if (! $node instanceof ObjectValueNode) {
            $this->error(sprintf('%s of type "%s" must be an input object.', $context, $type->name()), $blame);

            return;
        }

        $fields = $type->fields();
        $present = [];
        foreach ($node->fields as $objectField) {
            $present[$objectField->name] = true;
            if (! isset($fields[$objectField->name])) {
                $this->error(sprintf(
                    'Field "%s" is not defined on input type "%s".',
                    $objectField->name,
                    $type->name(),
                ), $objectField);

                continue;
            }
            $this->checkValue(
                $fields[$objectField->name]->getType(),
                $objectField->value,
                sprintf('Field "%s"', $objectField->name),
                $objectField,
            );
        }

        foreach ($fields as $field) {
            if ($field->getType() instanceof NonNull
                && ! $field->hasDefaultValue()
                && ! isset($present[$field->getName()])) {
                $this->error(sprintf(
                    'Field "%s" of required type "%s" was not provided.',
                    $field->getName(),
                    (string) $field->getType(),
                ), $blame);
            }
        }
    }

    /**
     * @param  array<string, true>  $visitedFragments
     */
    private function checkFragmentSpread(FragmentSpreadNode $spread, array $visitedFragments): void
    {
        if (! isset($this->fragments[$spread->name])) {
            $this->error(sprintf('Unknown fragment "%s".', $spread->name), $spread);

            return;
        }

        if (isset($visitedFragments[$spread->name])) {
            return;
        }

        $fragment = $this->fragments[$spread->name];
        $type = $this->compositeType($fragment->typeCondition->name);
        if ($type === null) {
            $this->error(sprintf('Unknown type "%s".', $fragment->typeCondition->name), $fragment);

            return;
        }

        $visitedFragments[$spread->name] = true;
        $this->checkSelectionSet($type, $fragment->selectionSet, $visitedFragments);
    }

    private function checkVariableUsage(OperationDefinitionNode $operation): void
    {
        $defined = [];
        foreach ($operation->variableDefinitions as $definition) {
            $defined[$definition->variable->name] = true;
        }

        $used = [];
        $this->collectUsedVariables($operation->selectionSet, $used, []);

        foreach ($used as $name => $_) {
            if (! isset($defined[$name])) {
                $this->error(sprintf('Variable "$%s" is not defined.', $name), $operation);
            }
        }

        foreach ($defined as $name => $_) {
            if (! isset($used[$name])) {
                $this->error(sprintf('Variable "$%s" is never used.', $name), $operation);
            }
        }
    }

    /**
     * @param  array<string, true>  $used
     * @param  array<string, true>  $visited
     */
    private function collectUsedVariables(SelectionSetNode $selectionSet, array &$used, array $visited): void
    {
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                foreach ($selection->arguments as $argument) {
                    $this->collectVariablesInValue($argument->value, $used);
                }
                if ($selection->selectionSet !== null) {
                    $this->collectUsedVariables($selection->selectionSet, $used, $visited);
                }
            } elseif ($selection instanceof InlineFragmentNode) {
                $this->collectUsedVariables($selection->selectionSet, $used, $visited);
            } elseif ($selection instanceof FragmentSpreadNode) {
                if (isset($visited[$selection->name]) || ! isset($this->fragments[$selection->name])) {
                    continue;
                }
                $visited[$selection->name] = true;
                $this->collectUsedVariables($this->fragments[$selection->name]->selectionSet, $used, $visited);
            }
        }
    }

    /**
     * @param  array<string, true>  $used
     */
    private function collectVariablesInValue(ValueNode $value, array &$used): void
    {
        if ($value instanceof VariableNode) {
            $used[$value->name] = true;
        } elseif ($value instanceof ListValueNode) {
            foreach ($value->values as $item) {
                $this->collectVariablesInValue($item, $used);
            }
        } elseif ($value instanceof ObjectValueNode) {
            foreach ($value->fields as $field) {
                $this->collectVariablesInValue($field->value, $used);
            }
        }
    }

    private function checkFragmentUsage(): void
    {
        $referenced = [];
        foreach ($this->document->definitions as $definition) {
            if ($definition instanceof OperationDefinitionNode) {
                $this->collectSpreadNames($definition->selectionSet, $referenced);
            }
        }
        foreach ($this->fragments as $fragment) {
            $this->collectSpreadNames($fragment->selectionSet, $referenced);
        }

        foreach ($this->fragments as $name => $fragment) {
            if (! isset($referenced[$name])) {
                $this->error(sprintf('Fragment "%s" is never used.', $name), $fragment);
            }
        }
    }

    /**
     * @param  array<string, true>  $names
     */
    private function collectSpreadNames(SelectionSetNode $selectionSet, array &$names): void
    {
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FragmentSpreadNode) {
                $names[$selection->name] = true;
            } elseif ($selection instanceof FieldNode && $selection->selectionSet !== null) {
                $this->collectSpreadNames($selection->selectionSet, $names);
            } elseif ($selection instanceof InlineFragmentNode) {
                $this->collectSpreadNames($selection->selectionSet, $names);
            }
        }
    }

    private function checkFragmentCycles(): void
    {
        foreach ($this->fragments as $name => $fragment) {
            $this->detectCycle($name, [], []);
        }
    }

    /**
     * @param  array<string, true>  $onStack
     * @param  array<string, true>  $seen
     */
    private function detectCycle(string $name, array $onStack, array $seen): void
    {
        if (isset($onStack[$name])) {
            $this->error(sprintf('Cannot spread fragment "%s" — it forms a cycle.', $name), $this->fragments[$name]);

            return;
        }
        if (isset($seen[$name]) || ! isset($this->fragments[$name])) {
            return;
        }

        $onStack[$name] = true;
        $spreads = [];
        $this->collectSpreadNames($this->fragments[$name]->selectionSet, $spreads);
        foreach ($spreads as $spread => $_) {
            $this->detectCycle($spread, $onStack, $seen);
        }
    }

    private function compositeType(string $name): ?CompositeType
    {
        $type = $this->schema->getType($name);

        return $type instanceof CompositeType ? $type : null;
    }

    private function error(string $message, ?Node $node = null): void
    {
        $this->errors[] = new GraphQLError($message, nodes: $node !== null ? [$node] : []);
    }
}
