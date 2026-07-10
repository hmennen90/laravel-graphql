<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/** Scaffolds a code-first GraphQL object type (attribute-driven). */
#[AsCommand(name: 'make:graphql-type')]
final class MakeTypeCommand extends GeneratorCommand
{
    protected $name = 'make:graphql-type';

    protected $description = 'Create a new code-first GraphQL type';

    protected $type = 'Type';

    #[\Override]
    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/graphql/type.stub');

        return is_file($published) ? $published : __DIR__.'/stubs/type.stub';
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\GraphQL\\Types';
    }
}
