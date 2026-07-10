<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Fixtures;

use Hmennen90\GraphQL\Contracts\ProvidesSchema;
use Hmennen90\GraphQL\Directives\CanDirective;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Schema\Schema;

final class CanSchema implements ProvidesSchema
{
    public function schema(): Schema
    {
        return SchemaBuilder::fromSdl(
            <<<'GRAPHQL'
                directive @can(ability: String!) on FIELD_DEFINITION
                type Query {
                  public: String
                  secret: String @can(ability: "view-secret")
                }
                GRAPHQL,
            resolvers: [
                'Query' => [
                    'public' => fn (): string => 'open',
                    'secret' => fn (): string => 'classified',
                ],
            ],
            schemaDirectives: ['can' => new CanDirective()],
        );
    }
}
