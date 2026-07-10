<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\SchemaFirst;

use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Language\Source;
use Hmennen90\GraphQL\Engine\Schema\Schema;

/** Facade for building a {@see Schema} from SDL plus a resolver map. */
final class SchemaBuilder
{
    /**
     * @param  array<string, array<string, callable>>  $resolvers  type => (field => resolver)
     * @param  array<string, callable>  $typeResolvers  abstract type => resolveType callback
     * @param  array<string, SchemaDirective|ArgumentDirective>  $schemaDirectives  SDL directive name => build-time handler
     */
    public static function fromSdl(
        string|Source $sdl,
        array $resolvers = [],
        array $typeResolvers = [],
        array $schemaDirectives = [],
        ?callable $fallbackResolver = null,
    ): Schema {
        return self::fromDocument(Parser::parse($sdl), $resolvers, $typeResolvers, $schemaDirectives, $fallbackResolver);
    }

    /**
     * Build from an already-parsed document (e.g. a cached AST).
     *
     * @param  array<string, array<string, callable>>  $resolvers
     * @param  array<string, callable>  $typeResolvers
     * @param  array<string, SchemaDirective|ArgumentDirective>  $schemaDirectives
     * @param  (callable(string, string): ?callable)|null  $fallbackResolver  consulted when no explicit field resolver exists
     */
    public static function fromDocument(
        DocumentNode $document,
        array $resolvers = [],
        array $typeResolvers = [],
        array $schemaDirectives = [],
        ?callable $fallbackResolver = null,
    ): Schema {
        return new AstToSchema($document, new ResolverMap($resolvers, $typeResolvers, $fallbackResolver), $schemaDirectives)->build();
    }
}
