<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\CodeFirst;

use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLField;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLType;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\OutputType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Builds object types from PHP classes annotated with {@see GraphQLType} and
 * {@see GraphQLField} attributes. Each annotated method becomes a field whose
 * resolver invokes that method.
 */
final class AttributeSchemaBuilder
{
    /** @var array<string, ObjectType> */
    private array $registry = [];

    /**
     * @param  array<int, class-string>  $classes
     * @return array<string, ObjectType>
     */
    public function build(array $classes): array
    {
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(GraphQLType::class);
            if ($attributes === []) {
                continue;
            }

            $meta = $attributes[0]->newInstance();
            $name = $meta->name ?? $reflection->getShortName();
            $this->registry[$name] = new ObjectType(
                $name,
                fn (): array => $this->fields($reflection),
                [],
                $meta->description,
            );
        }

        return $this->registry;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return array<int, FieldDefinition>
     */
    private function fields(ReflectionClass $reflection): array
    {
        $fields = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(GraphQLField::class);
            if ($attributes === []) {
                continue;
            }

            $meta = $attributes[0]->newInstance();
            $fieldName = $meta->name ?? $method->getName();
            $methodName = $method->getName();
            $className = $reflection->getName();

            $fields[] = FieldDefinition::make(
                $fieldName,
                TypeExpression::parse($meta->type, fn (string $n): Type&OutputType => $this->resolveNamed($n)),
                resolve: static function (mixed $source, array $args, mixed $context, mixed $info) use ($className, $methodName): mixed {
                    /** @var object $instance */
                    $instance = new $className();

                    return $instance->{$methodName}($source, $args, $context, $info);
                },
                description: $meta->description,
            );
        }

        return $fields;
    }

    private function resolveNamed(string $name): Type&OutputType
    {
        $scalars = Type::builtInScalars();
        if (isset($scalars[$name])) {
            return $scalars[$name];
        }

        if (isset($this->registry[$name])) {
            return $this->registry[$name];
        }

        throw new RuntimeException(sprintf('Unknown GraphQL type "%s" referenced in an attribute.', $name));
    }
}
