<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/** Scaffolds a build-time schema directive. */
#[AsCommand(name: 'make:graphql-directive')]
final class MakeDirectiveCommand extends GeneratorCommand
{
    protected $name = 'make:graphql-directive';

    protected $description = 'Create a new GraphQL schema directive';

    protected $type = 'Directive';

    #[\Override]
    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/graphql/directive.stub');

        return is_file($published) ? $published : __DIR__.'/stubs/directive.stub';
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\GraphQL\\Directives';
    }
}
