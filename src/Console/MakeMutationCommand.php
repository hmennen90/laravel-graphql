<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/** Scaffolds a GraphQL Mutation. */
#[AsCommand(name: 'make:graphql-mutation')]
final class MakeMutationCommand extends GeneratorCommand
{
    protected $name = 'make:graphql-mutation';

    protected $description = 'Create a new GraphQL Mutation';

    protected $type = 'Mutation';

    #[\Override]
    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/graphql/mutation.stub');

        return is_file($published) ? $published : __DIR__.'/stubs/mutation.stub';
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\GraphQL\\Mutations';
    }
}
