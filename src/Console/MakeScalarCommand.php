<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/** Scaffolds a GraphQL Scalar. */
#[AsCommand(name: 'make:graphql-scalar')]
final class MakeScalarCommand extends GeneratorCommand
{
    protected $name = 'make:graphql-scalar';

    protected $description = 'Create a new GraphQL Scalar';

    protected $type = 'Scalar';

    #[\Override]
    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/graphql/scalar.stub');

        return is_file($published) ? $published : __DIR__.'/stubs/scalar.stub';
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\GraphQL\\Scalars';
    }
}
