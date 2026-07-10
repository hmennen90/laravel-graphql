<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\SchemaFirst;

use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Language\Source;
use Hmennen90\GraphQL\Engine\Schema\Schema;

/** Facade for building a {@see Schema} from SDL plus a resolver map. */
final class SchemaBuilder
{
    /**
     * @param  array<string, array<string, callable>>  $resolvers  type => (field => resolver)
     * @param  array<string, callable>  $typeResolvers  abstract type => resolveType callback
     * @param  array<string, SchemaDirective>  $schemaDirectives  SDL directive name => build-time handler
     */
    public static function fromSdl(
        string|Source $sdl,
        array $resolvers = [],
        array $typeResolvers = [],
        array $schemaDirectives = [],
    ): Schema {
        $document = Parser::parse($sdl);

        return (new AstToSchema($document, new ResolverMap($resolvers, $typeResolvers), $schemaDirectives))->build();
    }
}
