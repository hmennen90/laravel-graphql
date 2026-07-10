<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/** Scaffolds a GraphQL Query. */
#[AsCommand(name: 'make:graphql-query')]
final class MakeQueryCommand extends GeneratorCommand
{
    protected $name = 'make:graphql-query';

    protected $description = 'Create a new GraphQL Query';

    protected $type = 'Query';

    #[\Override]
    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/graphql/query.stub');

        return is_file($published) ? $published : __DIR__.'/stubs/query.stub';
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\GraphQL\\Queries';
    }
}
