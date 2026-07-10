<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Federation;

use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Schema\SchemaPrinter;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\CustomScalarType;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Type\Definition\UnionType;

/**
 * Turns a schema into an Apollo Federation subgraph: adds the `_service { sdl }`
 * and `_entities(representations: [_Any!]!)` query fields plus the `_Any`,
 * `_Service` and `_Entity` types, with per-type entity reference resolvers.
 *
 * @phpstan-type EntityConfig array{model: class-string, resolve: callable(array<string, mixed>): mixed, keys?: string|list<string>, key?: string|list<string>, shareable?: list<string>, external?: list<string>, requires?: array<string, string>, provides?: array<string, string>}
 */
final class Federation
{
    /**
     * @param  array<string, array{model: class-string, resolve: callable(array<string, mixed>): mixed, keys?: string|list<string>, key?: string|list<string>, shareable?: list<string>, external?: list<string>, requires?: array<string, string>, provides?: array<string, string>}>  $entities
     */
    public static function subgraph(Schema $base, array $entities): Schema
    {
        $query = $base->getQueryType();
        if ($query === null) {
            throw new \RuntimeException('A federated schema requires a query type.');
        }

        /** @var array<string, class-string> $classByType */
        $classByType = [];
        $entityTypes = [];
        foreach ($entities as $typeName => $config) {
            $type = $base->getType($typeName);
            if ($type instanceof ObjectType) {
                $entityTypes[] = $type;
                $classByType[$typeName] = $config['model'];
            }
        }

        $entityUnion = new UnionType(
            '_Entity',
            $entityTypes,
            resolveType: static function (mixed $value) use ($entityTypes, $classByType): ?ObjectType {
                foreach ($entityTypes as $type) {
                    $class = $classByType[$type->name()] ?? null;
                    if ($class !== null && $value instanceof $class) {
                        return $type;
                    }
                }

                return null;
            },
        );

        $any = new CustomScalarType('_Any', parseValue: static fn (mixed $v): mixed => $v, serialize: static fn (mixed $v): mixed => $v);
        $service = new ObjectType('_Service', [
            FieldDefinition::make('sdl', Type::nonNull(Type::string())),
        ]);

        $sdl = SchemaPrinter::print($base, FederationAnnotations::fromConfig($entities));

        $fields = array_values($query->fields());
        $fields[] = FieldDefinition::make('_service', Type::nonNull($service), static fn (): array => ['sdl' => $sdl]);
        $fields[] = FieldDefinition::make(
            '_entities',
            Type::nonNull(Type::listOf($entityUnion)),
            static function ($root, array $args) use ($entities): array {
                $representations = $args['representations'] ?? [];
                $out = [];
                foreach (is_array($representations) ? $representations : [] as $representation) {
                    $out[] = self::resolveEntity($entities, $representation);
                }

                return $out;
            },
            [Argument::make('representations', Type::nonNull(Type::listOf(Type::nonNull($any))))],
        );

        $types = array_values($base->getTypeMap());
        $types[] = $service;
        $types[] = $entityUnion;
        $types[] = $any;

        return new Schema(new SchemaConfig(
            query: new ObjectType($query->name(), $fields),
            mutation: $base->getMutationType(),
            subscription: $base->getSubscriptionType(),
            types: $types,
        ));
    }

    /**
     * @param  array<string, array{model: class-string, resolve: callable(array<string, mixed>): mixed}>  $entities
     */
    private static function resolveEntity(array $entities, mixed $representation): mixed
    {
        if (! is_array($representation)) {
            return null;
        }
        $typeName = $representation['__typename'] ?? null;
        if (! is_string($typeName) || ! isset($entities[$typeName])) {
            return null;
        }

        /** @var array<string, mixed> $representation */
        return ($entities[$typeName]['resolve'])($representation);
    }
}
