<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

use Hmennen90\GraphQL\Engine\Language\AST\BooleanValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentSpreadNode;
use Hmennen90\GraphQL\Engine\Language\AST\InlineFragmentNode;
use Hmennen90\GraphQL\Engine\Language\AST\SelectionSetNode;
use Hmennen90\GraphQL\Engine\Language\AST\VariableNode;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\UnionType;

/**
 * Groups a selection set into response-key → field nodes, honouring
 * `@skip`/`@include` and expanding fragments that apply to the runtime type.
 */
final class FieldCollector
{
    /** @var array<string, array<int, FieldNode>> */
    private array $groups = [];

    /** @var array<string, true> */
    private array $visitedFragments = [];

    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, FragmentDefinitionNode>  $fragments
     */
    public function __construct(
        private readonly Schema $schema,
        private readonly array $variables,
        private readonly array $fragments,
    ) {
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, FragmentDefinitionNode>  $fragments
     * @return array<string, array<int, FieldNode>>
     */
    public static function collect(
        Schema $schema,
        ObjectType $objectType,
        SelectionSetNode $selectionSet,
        array $variables,
        array $fragments,
    ): array {
        $collector = new self($schema, $variables, $fragments);
        $collector->walk($objectType, $selectionSet);

        return $collector->groups;
    }

    private function walk(ObjectType $objectType, SelectionSetNode $selectionSet): void
    {
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                if (! $this->shouldInclude($selection->directives)) {
                    continue;
                }
                $this->groups[$selection->responseKey()][] = $selection;
            } elseif ($selection instanceof InlineFragmentNode) {
                if (! $this->shouldInclude($selection->directives)) {
                    continue;
                }
                if ($selection->typeCondition !== null
                    && ! $this->typeApplies($objectType, $selection->typeCondition->name)) {
                    continue;
                }
                $this->walk($objectType, $selection->selectionSet);
            } elseif ($selection instanceof FragmentSpreadNode) {
                if (! $this->shouldInclude($selection->directives) || isset($this->visitedFragments[$selection->name])) {
                    continue;
                }
                $this->visitedFragments[$selection->name] = true;
                $fragment = $this->fragments[$selection->name] ?? null;
                if ($fragment === null || ! $this->typeApplies($objectType, $fragment->typeCondition->name)) {
                    continue;
                }
                $this->walk($objectType, $fragment->selectionSet);
            }
        }
    }

    private function typeApplies(ObjectType $objectType, string $conditionName): bool
    {
        if ($conditionName === $objectType->name()) {
            return true;
        }

        $conditionType = $this->schema->getType($conditionName);

        if ($conditionType instanceof InterfaceType) {
            return $objectType->implementsInterface($conditionName);
        }

        if ($conditionType instanceof UnionType) {
            return $conditionType->hasType($objectType->name());
        }

        return false;
    }

    /**
     * @param  array<int, DirectiveNode>  $directives
     */
    private function shouldInclude(array $directives): bool
    {
        foreach ($directives as $directive) {
            if ($directive->name === 'skip' && $this->directiveIf($directive) === true) {
                return false;
            }
            if ($directive->name === 'include' && $this->directiveIf($directive) === false) {
                return false;
            }
        }

        return true;
    }

    private function directiveIf(DirectiveNode $directive): ?bool
    {
        foreach ($directive->arguments as $argument) {
            if ($argument->name !== 'if') {
                continue;
            }
            $value = $argument->value;
            if ($value instanceof BooleanValueNode) {
                return $value->value;
            }
            if ($value instanceof VariableNode) {
                $resolved = $this->variables[$value->name] ?? null;

                return is_bool($resolved) ? $resolved : null;
            }
        }

        return null;
    }
}
