<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http;

use Hmennen90\GraphQL\Engine\Error\SyntaxError;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentSpreadNode;
use Hmennen90\GraphQL\Engine\Language\AST\InlineFragmentNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationType;
use Hmennen90\GraphQL\Engine\Language\AST\SelectionSetNode;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Type\Definition\CompositeType;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/**
 * Computes an HTTP cache hint (max-age + scope) for a query from the
 * `@cacheControl` metadata on the fields it selects. A response is cacheable only
 * when every selected field carries a hint; the result max-age is their minimum
 * and the scope is PRIVATE if any field is private.
 */
final class CacheControlAnalyzer
{
    /** @var array<string, FragmentDefinitionNode> */
    private array $fragments = [];

    /**
     * @return array{maxAge: int, scope: string}|null  null when the operation is not a cacheable query
     */
    public function analyze(Schema $schema, string $query, ?string $operationName): ?array
    {
        try {
            $document = Parser::parse($query);
        } catch (SyntaxError) {
            return null;
        }

        $operation = null;
        $this->fragments = [];
        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $this->fragments[$definition->name] = $definition;
            } elseif ($definition instanceof OperationDefinitionNode
                && ($operationName === null || $definition->name === $operationName)) {
                $operation ??= $definition;
            }
        }

        if ($operation === null || $operation->operation !== OperationType::QUERY) {
            return null;
        }

        $queryType = $schema->getQueryType();
        if ($queryType === null) {
            return null;
        }

        $maxAge = PHP_INT_MAX;
        $scope = 'PUBLIC';
        $found = false;
        $uncacheable = false;

        $this->walk($schema, $queryType, $operation->selectionSet, [], $maxAge, $scope, $found, $uncacheable);

        if (! $found || $uncacheable) {
            return ['maxAge' => 0, 'scope' => $scope];
        }

        return ['maxAge' => $maxAge, 'scope' => $scope];
    }

    /**
     * @param  array<string, true>  $visited
     */
    private function walk(Schema $schema, CompositeType $parentType, SelectionSetNode $set, array $visited, int &$maxAge, string &$scope, bool &$found, bool &$uncacheable): void
    {
        foreach ($set->selections as $selection) {
            if ($selection instanceof FieldNode) {
                if (str_starts_with($selection->name, '__')) {
                    continue;
                }
                if (! ($parentType instanceof ObjectType || $parentType instanceof InterfaceType) || ! $parentType->hasField($selection->name)) {
                    continue;
                }

                $field = $parentType->getField($selection->name);
                $hint = $field->metadata('cacheMaxAge');
                if (is_int($hint)) {
                    $found = true;
                    $maxAge = min($maxAge, $hint);
                    if ($field->metadata('cacheScope') === 'PRIVATE') {
                        $scope = 'PRIVATE';
                    }
                } else {
                    $uncacheable = true;
                }

                $namedType = Type::getNamedType($field->getType());
                if ($selection->selectionSet !== null && $namedType instanceof CompositeType) {
                    $this->walk($schema, $namedType, $selection->selectionSet, $visited, $maxAge, $scope, $found, $uncacheable);
                }
            } elseif ($selection instanceof InlineFragmentNode) {
                $type = $selection->typeCondition !== null ? $schema->getType($selection->typeCondition->name) : $parentType;
                if ($type instanceof CompositeType) {
                    $this->walk($schema, $type, $selection->selectionSet, $visited, $maxAge, $scope, $found, $uncacheable);
                }
            } elseif ($selection instanceof FragmentSpreadNode && ! isset($visited[$selection->name])) {
                $fragment = $this->fragments[$selection->name] ?? null;
                if ($fragment === null) {
                    continue;
                }
                $type = $schema->getType($fragment->typeCondition->name);
                if ($type instanceof CompositeType) {
                    $this->walk($schema, $type, $fragment->selectionSet, [...$visited, $selection->name => true], $maxAge, $scope, $found, $uncacheable);
                }
            }
        }
    }
}
