<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Fixtures;

use Hmennen90\GraphQL\Contracts\ProvidesSchema;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Execution\Context;
use RuntimeException;

final class TestSchema implements ProvidesSchema
{
    public function schema(): Schema
    {
        $user = new ObjectType('User', [
            FieldDefinition::make('id', Type::nonNull(Type::id())),
            FieldDefinition::make('name', Type::string()),
        ]);

        $query = new ObjectType('Query', [
            FieldDefinition::make('hello', Type::nonNull(Type::string()), resolve: fn (): string => 'world'),
            FieldDefinition::make('echo', Type::nonNull(Type::string()),
                args: [Argument::make('msg', Type::nonNull(Type::string()))],
                resolve: fn ($root, array $args): string => (string) $args['msg']),
            FieldDefinition::make('me', $user, resolve: fn (): array => ['id' => '1', 'name' => 'Ada']),
            FieldDefinition::make('secret', Type::string(), resolve: function ($root, array $args, mixed $context): string {
                if ($context instanceof Context) {
                    $context->authorize('view-secret');
                }

                return 'top-secret';
            }),
            FieldDefinition::make('boom', Type::string(), resolve: function (): string {
                throw new RuntimeException('internal detail leak');
            }),
        ]);

        $post = new ObjectType('Post', [
            FieldDefinition::make('id', Type::nonNull(Type::id())),
            FieldDefinition::make('title', Type::string()),
        ]);

        $subscription = new ObjectType('Subscription', [
            FieldDefinition::make('postAdded', $post, resolve: fn (mixed $root): mixed => $root),
        ]);

        return new Schema(new SchemaConfig(query: $query, subscription: $subscription));
    }
}
