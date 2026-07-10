<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Fixtures;

use Hmennen90\GraphQL\Contracts\ProvidesSchema;
use Hmennen90\GraphQL\Directives\CacheControlDirective;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Schema\Schema;

final class CacheControlSchema implements ProvidesSchema
{
    public function schema(): Schema
    {
        return SchemaBuilder::fromSdl(
            <<<'GRAPHQL'
                directive @cacheControl(maxAge: Int, scope: String) on FIELD_DEFINITION
                type Query {
                  profile: Profile @cacheControl(maxAge: 60, scope: "PUBLIC")
                  uncached: String
                }
                type Profile {
                  id: ID! @cacheControl(maxAge: 60)
                  name: String @cacheControl(maxAge: 30)
                }
                GRAPHQL,
            resolvers: [
                'Query' => [
                    'profile' => fn (): array => ['id' => '1', 'name' => 'Ada'],
                    'uncached' => fn (): string => 'x',
                ],
            ],
            schemaDirectives: ['cacheControl' => new CacheControlDirective()],
        );
    }
}
