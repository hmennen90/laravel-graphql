<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumType;
use Hmennen90\GraphQL\Engine\Type\Definition\EnumValueDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/** Shared helpers for filter/sort directives (column allow-lists, generated inputs). */
abstract readonly class FilterDirective implements SchemaDirective
{
    /**
     * @return array<int, string>
     */
    protected function columns(DirectiveNode $node): array
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name === 'columns' && $argument->value instanceof ListValueNode) {
                $columns = [];
                foreach ($argument->value->values as $value) {
                    if ($value instanceof StringValueNode) {
                        $columns[] = $value->value;
                    }
                }

                return $columns;
            }
        }

        return [];
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function columnEnum(SchemaBuildContext $context, string $name, array $columns): EnumType
    {
        $existing = $context->getType($name);
        if ($existing instanceof EnumType) {
            return $existing;
        }

        $enum = new EnumType($name, array_map(
            static fn (string $column): EnumValueDefinition => new EnumValueDefinition($column),
            $columns,
        ));
        $context->registerType($enum);

        return $enum;
    }

    /**
     * @param  callable(): (Type&NamedType)  $factory
     */
    protected function register(SchemaBuildContext $context, string $name, callable $factory): Type&NamedType
    {
        $existing = $context->getType($name);
        if ($existing !== null) {
            return $existing;
        }

        $type = $factory();
        $context->registerType($type);

        return $type;
    }

    protected function prefix(SchemaBuildContext $context): string
    {
        return $context->parentTypeName.ucfirst($context->fieldName);
    }
}
