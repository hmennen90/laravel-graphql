<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Eloquent;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Resolves the Eloquent model class backing a GraphQL type — from an explicit
 * class name or, by convention, `<namespace>\<TypeName>`. This keeps the model
 * the single source of truth: directives derive queries from it, no re-declaration.
 */
final readonly class ModelResolver
{
    public function __construct(private string $namespace = 'App\\Models')
    {
    }

    /**
     * @return class-string<Model>
     */
    public function resolve(string $modelOrType): string
    {
        $candidates = [
            $modelOrType,
            $this->namespace.'\\'.$modelOrType,
        ];

        foreach ($candidates as $class) {
            if (class_exists($class) && is_a($class, Model::class, true)) {
                return $class;
            }
        }

        throw new InvalidArgumentException(sprintf('Could not resolve an Eloquent model for "%s".', $modelOrType));
    }
}
