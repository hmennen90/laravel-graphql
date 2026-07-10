<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Support\Relay;

use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/**
 * Relay-style connection helpers: cursor encoding, connection data building and
 * connection/edge/pageInfo type factories (offset-based cursors).
 */
final class Relay
{
    private const string PREFIX = 'graphql:cursor:';

    public static function cursor(int $offset): string
    {
        return base64_encode(self::PREFIX.$offset);
    }

    /** Encodes a Relay global object identifier, `base64("Type:id")`. */
    public static function toGlobalId(string $type, string|int $id): string
    {
        return base64_encode($type.':'.$id);
    }

    /**
     * Decodes a Relay global object identifier into its type and id parts.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function fromGlobalId(string $globalId): array
    {
        $decoded = base64_decode($globalId, true);
        if ($decoded === false || ! str_contains($decoded, ':')) {
            return [null, null];
        }

        [$type, $id] = explode(':', $decoded, 2);

        return [$type, $id];
    }

    public static function offset(string $cursor): ?int
    {
        $decoded = base64_decode($cursor, true);
        if ($decoded === false || ! str_starts_with($decoded, self::PREFIX)) {
            return null;
        }

        $value = substr($decoded, strlen(self::PREFIX));

        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $args  first, last, after, before
     * @return array{edges: array<int, array{node: mixed, cursor: string}>, pageInfo: array{hasNextPage: bool, hasPreviousPage: bool, startCursor: ?string, endCursor: ?string}, totalCount: int}
     */
    public static function connectionFromArray(array $items, array $args): array
    {
        $items = array_values($items);
        $total = count($items);

        $start = 0;
        $end = $total;

        if (isset($args['after']) && is_string($args['after'])) {
            $after = self::offset($args['after']);
            if ($after !== null) {
                $start = max($start, $after + 1);
            }
        }
        if (isset($args['before']) && is_string($args['before'])) {
            $before = self::offset($args['before']);
            if ($before !== null) {
                $end = min($end, $before);
            }
        }
        if (isset($args['first']) && is_int($args['first'])) {
            $end = min($end, $start + $args['first']);
        }
        if (isset($args['last']) && is_int($args['last'])) {
            $start = max($start, $end - $args['last']);
        }

        $edges = [];
        for ($i = $start; $i < $end; $i++) {
            $edges[] = ['node' => $items[$i], 'cursor' => self::cursor($i)];
        }

        return [
            'edges' => $edges,
            'pageInfo' => [
                'hasNextPage' => $end < $total,
                'hasPreviousPage' => $start > 0,
                'startCursor' => $edges !== [] ? $edges[0]['cursor'] : null,
                'endCursor' => $edges !== [] ? $edges[count($edges) - 1]['cursor'] : null,
            ],
            'totalCount' => $total,
        ];
    }

    public static function pageInfoType(): ObjectType
    {
        return new ObjectType('PageInfo', [
            FieldDefinition::make('hasNextPage', Type::nonNull(Type::boolean())),
            FieldDefinition::make('hasPreviousPage', Type::nonNull(Type::boolean())),
            FieldDefinition::make('startCursor', Type::string()),
            FieldDefinition::make('endCursor', Type::string()),
        ]);
    }

    public static function edgeType(ObjectType $node): ObjectType
    {
        return new ObjectType($node->name().'Edge', [
            FieldDefinition::make('node', $node),
            FieldDefinition::make('cursor', Type::nonNull(Type::string())),
        ]);
    }

    public static function connectionType(ObjectType $node): ObjectType
    {
        return new ObjectType($node->name().'Connection', [
            FieldDefinition::make('edges', Type::listOf(Type::nonNull(self::edgeType($node)))),
            FieldDefinition::make('pageInfo', Type::nonNull(self::pageInfoType())),
            FieldDefinition::make('totalCount', Type::int()),
        ]);
    }
}
