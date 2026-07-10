<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Validation;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Error\GraphQLError;
use Hmennen90\GraphQL\Engine\Language\AST\BooleanValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
use Hmennen90\GraphQL\Engine\Language\AST\EnumValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\FloatValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentSpreadNode;
use Hmennen90\GraphQL\Engine\Language\AST\InlineFragmentNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\NamedTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\Node;
use Hmennen90\GraphQL\Engine\Language\AST\NonNullTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\NullValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationType;
use Hmennen90\GraphQL\Engine\Language\AST\SelectionSetNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\TypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\VariableNode;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Type\Definition\AbstractType;
use Hmennen90\GraphQL\Engine\Type\Definition\CompositeType;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\InputType;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\LeafType;
use Hmennen90\GraphQL\Engine\Type\Definition\ListOfType;
use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
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

    /** @var array<int, array{type: Type, name: string, node: Node}> */
    private array $variableUsages = [];

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
        $this->checkUniqueOperationNames();
        $this->checkLoneAnonymousOperation();
        $this->checkUniqueFragmentNames();
        $this->checkFragmentDefinitions();

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

        $this->checkDirectives($operation->directives, strtoupper($operation->operation->value));
        $this->checkUniqueVariableNames($operation);
        $this->checkVariablesAreInputTypes($operation);

        $this->variableUsages = [];
        $this->checkSelectionSet($rootType, $operation->selectionSet, []);
        $this->checkVariableUsage($operation);
        $this->checkVariablesInAllowedPosition($operation);
    }

    /**
     * @param  array<string, true>  $visitedFragments
     */
    private function checkSelectionSet(CompositeType $parentType, SelectionSetNode $selectionSet, array $visitedFragments): void
    {
        $this->checkFieldMerging($parentType, $selectionSet);

        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                $this->checkField($parentType, $selection, $visitedFragments);
            } elseif ($selection instanceof InlineFragmentNode) {
                $this->checkDirectives($selection->directives, 'INLINE_FRAGMENT');
                $type = $parentType;
                if ($selection->typeCondition !== null) {
                    $name = $selection->typeCondition->name;
                    $namedType = $this->schema->getType($name);
                    if ($namedType === null) {
                        $this->error(sprintf('Unknown type "%s".', $name), $selection);

                        continue;
                    }
                    if (! $namedType instanceof CompositeType) {
                        $this->error(sprintf('Fragment cannot condition on non composite type "%s".', $name), $selection);

                        continue;
                    }
                    if (! $this->doTypesOverlap($parentType, $namedType)) {
                        $this->error(sprintf(
                            'Fragment cannot be spread here as objects of type "%s" can never be of type "%s".',
                            $parentType->name(),
                            $name,
                        ), $selection);

                        continue;
                    }
                    $type = $namedType;
                }
                $this->checkSelectionSet($type, $selection->selectionSet, $visitedFragments);
            } elseif ($selection instanceof FragmentSpreadNode) {
                $this->checkDirectives($selection->directives, 'FRAGMENT_SPREAD');
                $this->checkFragmentSpread($selection, $parentType, $visitedFragments);
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

        $this->checkDirectives($field->directives, 'FIELD');

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
            if (isset($provided[$argument->name])) {
                $this->error(sprintf('There can be only one argument named "%s".', $argument->name), $argument);
            }
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
            $this->collectVariableUsages($argDef->getType(), $argument->value);
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
            if (isset($present[$objectField->name])) {
                $this->error(sprintf('There can be only one input field named "%s".', $objectField->name), $objectField);
            }
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
    private function checkFragmentSpread(FragmentSpreadNode $spread, CompositeType $parentType, array $visitedFragments): void
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
            return; // reported by checkFragmentDefinitions()
        }

        if (! $this->doTypesOverlap($parentType, $type)) {
            $this->error(sprintf(
                'Fragment "%s" cannot be spread here as objects of type "%s" can never be of type "%s".',
                $spread->name,
                $parentType->name(),
                $type->name(),
            ), $spread);

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

    private function checkUniqueOperationNames(): void
    {
        $seen = [];
        foreach ($this->document->definitions as $definition) {
            if ($definition instanceof OperationDefinitionNode && $definition->name !== null) {
                if (isset($seen[$definition->name])) {
                    $this->error(sprintf('There can be only one operation named "%s".', $definition->name), $definition);
                }
                $seen[$definition->name] = true;
            }
        }
    }

    private function checkLoneAnonymousOperation(): void
    {
        $operations = [];
        foreach ($this->document->definitions as $definition) {
            if ($definition instanceof OperationDefinitionNode) {
                $operations[] = $definition;
            }
        }

        if (count($operations) < 2) {
            return;
        }

        foreach ($operations as $operation) {
            if ($operation->name === null) {
                $this->error('This anonymous operation must be the only defined operation.', $operation);
            }
        }
    }

    private function checkUniqueFragmentNames(): void
    {
        $seen = [];
        foreach ($this->document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                if (isset($seen[$definition->name])) {
                    $this->error(sprintf('There can be only one fragment named "%s".', $definition->name), $definition);
                }
                $seen[$definition->name] = true;
            }
        }
    }

    private function checkFragmentDefinitions(): void
    {
        foreach ($this->fragments as $fragment) {
            $this->checkDirectives($fragment->directives, 'FRAGMENT_DEFINITION');

            $name = $fragment->typeCondition->name;
            $type = $this->schema->getType($name);
            if ($type === null) {
                $this->error(sprintf('Unknown type "%s".', $name), $fragment);
            } elseif (! $type instanceof CompositeType) {
                $this->error(sprintf(
                    'Fragment "%s" can only be on an object, interface or union, not "%s".',
                    $fragment->name,
                    $name,
                ), $fragment);
            }
        }
    }

    private function checkUniqueVariableNames(OperationDefinitionNode $operation): void
    {
        $seen = [];
        foreach ($operation->variableDefinitions as $definition) {
            $name = $definition->variable->name;
            if (isset($seen[$name])) {
                $this->error(sprintf('There can be only one variable named "$%s".', $name), $definition);
            }
            $seen[$name] = true;
        }
    }

    private function checkVariablesAreInputTypes(OperationDefinitionNode $operation): void
    {
        foreach ($operation->variableDefinitions as $definition) {
            $type = $this->typeFromAst($definition->type);
            if ($type === null) {
                $this->error(sprintf('Unknown type "%s".', $this->namedTypeName($definition->type)), $definition);

                continue;
            }

            $named = Type::getNamedType($type);
            if (! $named instanceof InputType) {
                $this->error(sprintf(
                    'Variable "$%s" of type "%s" is not an input type.',
                    $definition->variable->name,
                    $named->name(),
                ), $definition);
            }
        }
    }

    private function checkVariablesInAllowedPosition(OperationDefinitionNode $operation): void
    {
        $defined = [];
        foreach ($operation->variableDefinitions as $definition) {
            $defined[$definition->variable->name] = [
                'type' => $this->typeFromAst($definition->type),
                'hasDefault' => $definition->defaultValue !== null,
            ];
        }

        foreach ($this->variableUsages as $usage) {
            $definition = $defined[$usage['name']] ?? null;
            if ($definition === null || $definition['type'] === null) {
                continue;
            }

            if (! $this->areTypesCompatible($definition['type'], $usage['type'], $definition['hasDefault'])) {
                $this->error(sprintf(
                    'Variable "$%s" of type "%s" cannot be used in position expecting type "%s".',
                    $usage['name'],
                    (string) $definition['type'],
                    (string) $usage['type'],
                ), $usage['node']);
            }
        }
    }

    private function collectVariableUsages(Type&InputType $type, ValueNode $value): void
    {
        if ($value instanceof VariableNode) {
            $this->variableUsages[] = ['type' => $type, 'name' => $value->name, 'node' => $value];

            return;
        }

        $nullable = $type instanceof NonNull ? $type->wrappedType() : $type;

        if ($value instanceof ListValueNode && $nullable instanceof ListOfType) {
            $inner = $nullable->wrappedType();
            if ($inner instanceof InputType) {
                foreach ($value->values as $item) {
                    $this->collectVariableUsages($inner, $item);
                }
            }
        } elseif ($value instanceof ObjectValueNode && $nullable instanceof InputObjectType) {
            $fields = $nullable->fields();
            foreach ($value->fields as $objectField) {
                if (isset($fields[$objectField->name])) {
                    $this->collectVariableUsages($fields[$objectField->name]->getType(), $objectField->value);
                }
            }
        }
    }

    private function areTypesCompatible(Type $varType, Type $locationType, bool $hasDefault): bool
    {
        if ($locationType instanceof NonNull) {
            if (! $varType instanceof NonNull) {
                return $hasDefault && $this->areTypesCompatible($varType, $locationType->wrappedType(), $hasDefault);
            }

            return $this->areTypesCompatible($varType->wrappedType(), $locationType->wrappedType(), $hasDefault);
        }

        if ($varType instanceof NonNull) {
            return $this->areTypesCompatible($varType->wrappedType(), $locationType, $hasDefault);
        }

        if ($locationType instanceof ListOfType) {
            return $varType instanceof ListOfType
                && $this->areTypesCompatible($varType->wrappedType(), $locationType->wrappedType(), $hasDefault);
        }

        if ($varType instanceof ListOfType) {
            return false;
        }

        return $this->typeName($varType) === $this->typeName($locationType);
    }

    /**
     * @param  array<int, \Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode>  $directives
     */
    private function checkDirectives(array $directives, string $location): void
    {
        $seen = [];
        foreach ($directives as $directive) {
            $def = $this->schema->getDirective($directive->name);
            if ($def === null) {
                $this->error(sprintf('Unknown directive "@%s".', $directive->name), $directive);

                continue;
            }

            if (! in_array($location, $def->locations(), true)) {
                $this->error(sprintf('Directive "@%s" cannot be used at this location.', $directive->name), $directive);
            }

            if (! $def->isRepeatable()) {
                if (isset($seen[$directive->name])) {
                    $this->error(sprintf('The directive "@%s" can only be used once per location.', $directive->name), $directive);
                }
                $seen[$directive->name] = true;
            }

            $provided = [];
            foreach ($directive->arguments as $argument) {
                $provided[$argument->name] = true;
                $argDef = $def->getArg($argument->name);
                if ($argDef === null) {
                    $this->error(sprintf('Unknown argument "%s" on directive "@%s".', $argument->name, $directive->name), $argument);

                    continue;
                }
                $this->checkValue($argDef->getType(), $argument->value, sprintf('Argument "%s"', $argument->name), $argument);
            }

            foreach ($def->args() as $argDef) {
                if ($argDef->getType() instanceof NonNull && ! $argDef->hasDefaultValue() && ! isset($provided[$argDef->getName()])) {
                    $this->error(sprintf(
                        'Directive "@%s" argument "%s" of type "%s" is required but not provided.',
                        $directive->name,
                        $argDef->getName(),
                        (string) $argDef->getType(),
                    ), $directive);
                }
            }
        }
    }

    private function doTypesOverlap(CompositeType $a, CompositeType $b): bool
    {
        $bNames = $this->possibleObjectNames($b);
        foreach ($this->possibleObjectNames($a) as $name) {
            if (in_array($name, $bNames, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function possibleObjectNames(CompositeType $type): array
    {
        if ($type instanceof ObjectType) {
            return [$type->name()];
        }

        if ($type instanceof AbstractType) {
            return array_map(static fn (ObjectType $t): string => $t->name(), $this->schema->getPossibleTypes($type));
        }

        return [];
    }

    private function typeFromAst(TypeNode $node): ?Type
    {
        if ($node instanceof NonNullTypeNode) {
            $inner = $this->typeFromAst($node->type);

            return $inner !== null ? Type::nonNull($inner) : null;
        }

        if ($node instanceof ListTypeNode) {
            $inner = $this->typeFromAst($node->type);

            return $inner !== null ? Type::listOf($inner) : null;
        }

        if ($node instanceof NamedTypeNode) {
            return $this->schema->getType($node->name);
        }

        return null;
    }

    private function namedTypeName(TypeNode $node): string
    {
        if ($node instanceof NonNullTypeNode || $node instanceof ListTypeNode) {
            return $this->namedTypeName($node->type);
        }
        if ($node instanceof NamedTypeNode) {
            return $node->name;
        }

        return '';
    }

    private function typeName(Type $type): ?string
    {
        return $type instanceof NamedType ? $type->name() : null;
    }

    private function checkFieldMerging(CompositeType $parentType, SelectionSetNode $set): void
    {
        /** @var array<string, array<int, FieldNode>> $groups */
        $groups = [];
        $this->collectApplicableFields($parentType, $set, $groups, []);

        foreach ($groups as $responseKey => $nodes) {
            if (count($nodes) < 2) {
                continue;
            }
            $first = $nodes[0];
            foreach (array_slice($nodes, 1) as $other) {
                if ($first->name !== $other->name) {
                    $this->error(sprintf(
                        'Fields "%s" conflict because "%s" and "%s" are different fields.',
                        $responseKey,
                        $first->name,
                        $other->name,
                    ), $other);

                    break;
                }
                if ($this->printArguments($first) !== $this->printArguments($other)) {
                    $this->error(sprintf(
                        'Fields "%s" conflict because they have differing arguments.',
                        $responseKey,
                    ), $other);

                    break;
                }
            }
        }
    }

    /**
     * Collect fields that definitely coexist on $parentType (self or a supertype
     * fragment), grouped by response key. Narrower type conditions are skipped to
     * avoid false conflicts across mutually exclusive types.
     *
     * @param  array<string, array<int, FieldNode>>  $groups
     * @param  array<string, true>  $visited
     */
    private function collectApplicableFields(CompositeType $parentType, SelectionSetNode $set, array &$groups, array $visited): void
    {
        foreach ($set->selections as $selection) {
            if ($selection instanceof FieldNode) {
                $groups[$selection->responseKey()][] = $selection;
            } elseif ($selection instanceof InlineFragmentNode) {
                if ($selection->typeCondition === null
                    || $this->conditionCoversParent($parentType, $selection->typeCondition->name)) {
                    $this->collectApplicableFields($parentType, $selection->selectionSet, $groups, $visited);
                }
            } elseif ($selection instanceof FragmentSpreadNode) {
                $fragment = $this->fragments[$selection->name] ?? null;
                if ($fragment === null || isset($visited[$selection->name])) {
                    continue;
                }
                if ($this->conditionCoversParent($parentType, $fragment->typeCondition->name)) {
                    $visited[$selection->name] = true;
                    $this->collectApplicableFields($parentType, $fragment->selectionSet, $groups, $visited);
                }
            }
        }
    }

    private function conditionCoversParent(CompositeType $parentType, string $conditionName): bool
    {
        if ($conditionName === $parentType->name()) {
            return true;
        }

        return $parentType instanceof ObjectType && $parentType->implementsInterface($conditionName);
    }

    private function printArguments(FieldNode $field): string
    {
        $parts = [];
        foreach ($field->arguments as $argument) {
            $parts[$argument->name] = $argument->name.':'.$this->printValue($argument->value);
        }
        ksort($parts);

        return implode(',', $parts);
    }

    private function printValue(ValueNode $value): string
    {
        return match (true) {
            $value instanceof VariableNode => '$'.$value->name,
            $value instanceof IntValueNode => 'i'.$value->value,
            $value instanceof FloatValueNode => 'f'.$value->value,
            $value instanceof StringValueNode => 's'.$value->value,
            $value instanceof BooleanValueNode => $value->value ? 'true' : 'false',
            $value instanceof EnumValueNode => 'e'.$value->value,
            $value instanceof NullValueNode => 'null',
            $value instanceof ListValueNode => '['.implode(',', array_map(fn (ValueNode $v): string => $this->printValue($v), $value->values)).']',
            $value instanceof ObjectValueNode => $this->printObjectValue($value),
            default => '?',
        };
    }

    private function printObjectValue(ObjectValueNode $value): string
    {
        $parts = [];
        foreach ($value->fields as $field) {
            $parts[] = $field->name.':'.$this->printValue($field->value);
        }
        sort($parts);

        return '{'.implode(',', $parts).'}';
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
