<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\CodeFirst;

use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLField;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLType;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\ProvidesDirective;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgumentDirective;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\OutputType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Builds object types from PHP classes annotated with {@see GraphQLType} and
 * {@see GraphQLField} attributes. Each annotated method becomes a field whose
 * resolver invokes that method. Directive attributes on a method (e.g. `#[All]`,
 * `#[Paginate]`) are the code-first equivalent of SDL directives and dispatch to
 * the exact same directive implementations.
 */
final class AttributeSchemaBuilder
{
    /** @var array<string, ObjectType> */
    private array $registry = [];

    /** @var array<string, Type&NamedType> Types registered by directives (paginators, filters, …). */
    private array $generated = [];

    /**
     * @param  array<string, SchemaDirective|ArgumentDirective>  $schemaDirectives
     */
    public function __construct(private readonly array $schemaDirectives = [])
    {
    }

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
                fn (): array => $this->fields($name, $reflection),
                [],
                $meta->description,
            );
        }

        // Force lazy field resolution so directive attributes run at build time and
        // can register their generated types (mirrors the SDL builder).
        if ($this->schemaDirectives !== []) {
            foreach ($this->registry as $type) {
                $type->fields();
            }
        }

        return $this->registry;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return array<int, FieldDefinition>
     */
    private function fields(string $typeName, ReflectionClass $reflection): array
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

            $field = FieldDefinition::make(
                $fieldName,
                TypeExpression::parse($meta->type, fn (string $n): Type&OutputType => $this->resolveNamed($n)),
                resolve: static function (mixed $source, array $args, mixed $context, mixed $info) use ($className, $methodName): mixed {
                    $instance = new $className();

                    return $instance->{$methodName}($source, $args, $context, $info);
                },
                description: $meta->description,
            );

            $field = $this->applyDirectiveAttributes($typeName, $fieldName, $method, $field);

            $fields[] = $field;
        }

        return $fields;
    }

    private function applyDirectiveAttributes(string $typeName, string $fieldName, ReflectionMethod $method, FieldDefinition $field): FieldDefinition
    {
        foreach ($method->getAttributes() as $attribute) {
            if (! is_a($attribute->getName(), ProvidesDirective::class, true)) {
                continue;
            }

            $instance = $attribute->newInstance();
            if (! $instance instanceof ProvidesDirective) {
                continue;
            }
            $node = $instance->toDirectiveNode();
            $directive = $this->schemaDirectives[$node->name] ?? null;
            if ($directive instanceof SchemaDirective) {
                $field = $directive->applyToField($field, $node, $this->buildContext($typeName, $fieldName));
            }
        }

        return $field;
    }

    private function buildContext(string $parentTypeName, string $fieldName): SchemaBuildContext
    {
        return new SchemaBuildContext(
            function (Type&NamedType $type): void {
                $this->generated[$type->name()] ??= $type;
            },
            fn (string $name): (Type&NamedType)|null => $this->registry[$name] ?? $this->generated[$name] ?? null,
            $parentTypeName,
            $fieldName,
        );
    }

    private function resolveNamed(string $name): Type&OutputType
    {
        $scalars = Type::builtInScalars();
        if (isset($scalars[$name])) {
            return $scalars[$name];
        }

        $type = $this->registry[$name] ?? $this->generated[$name] ?? null;
        if ($type instanceof OutputType) {
            return $type;
        }

        throw new RuntimeException(sprintf('Unknown GraphQL type "%s" referenced in an attribute.', $name));
    }
}
