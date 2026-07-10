<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Eloquent\Pagination;

use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/** Factories for Lighthouse-style paginator types (`{ data, paginatorInfo }`). */
final class PaginatorTypes
{
    public static function info(): ObjectType
    {
        return new ObjectType('PaginatorInfo', [
            FieldDefinition::make('count', Type::nonNull(Type::int())),
            FieldDefinition::make('currentPage', Type::nonNull(Type::int())),
            FieldDefinition::make('firstItem', Type::int()),
            FieldDefinition::make('hasMorePages', Type::nonNull(Type::boolean())),
            FieldDefinition::make('lastItem', Type::int()),
            FieldDefinition::make('lastPage', Type::nonNull(Type::int())),
            FieldDefinition::make('perPage', Type::nonNull(Type::int())),
            FieldDefinition::make('total', Type::nonNull(Type::int())),
        ]);
    }

    public static function paginator(ObjectType $node, ObjectType $info): ObjectType
    {
        return new ObjectType($node->name().'Paginator', [
            FieldDefinition::make('data', Type::nonNull(Type::listOf(Type::nonNull($node)))),
            FieldDefinition::make('paginatorInfo', Type::nonNull($info)),
        ]);
    }
}
