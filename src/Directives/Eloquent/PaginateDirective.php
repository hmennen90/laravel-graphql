<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Eloquent\ModelResolver;
use Hmennen90\GraphQL\Eloquent\Pagination\PaginatorTypes;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Support\Relay\Relay;
use LogicException;

/**
 * `@paginate(type: PAGINATOR|CONNECTION)` — turns a `[T!]!` field into a paginated
 * field: it registers a generated paginator/connection type, adds pagination
 * arguments, and resolves the page from the Eloquent model.
 */
final readonly class PaginateDirective extends EloquentDirective
{
    public function __construct(
        ModelResolver $models,
        private int $defaultCount = 15,
        private int $maxCount = 100,
    ) {
        parent::__construct($models);
    }

    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $node1 = Type::getNamedType($field->getType());
        if (! $node1 instanceof ObjectType) {
            throw new LogicException(sprintf('@paginate requires an object return type on "%s.%s".', $context->parentTypeName, $context->fieldName));
        }

        $modelClass = $this->models->resolve($this->stringArg($node, 'model') ?? $node1->name());
        $style = strtoupper($this->stringArg($node, 'type') ?? 'PAGINATOR');
        $default = $this->defaultCount;
        $max = $this->maxCount;

        if ($style === 'CONNECTION') {
            $connection = $this->register($context, $node1->name().'Connection', fn (): ObjectType => Relay::connectionType($node1));

            return FieldDefinition::make(
                $field->getName(),
                Type::nonNull($connection),
                static function ($root, array $args) use ($modelClass, $default, $max): array {
                    $first = min(is_int($args['first'] ?? null) ? $args['first'] : $default, $max);

                    return Relay::connectionFromArray(
                        $modelClass::query()->get()->all(),
                        ['first' => $first, 'after' => $args['after'] ?? null],
                    );
                },
                [...array_values($field->args()), Argument::make('first', Type::int()), Argument::make('after', Type::string())],
                $field->description(),
            );
        }

        $info = $this->register($context, 'PaginatorInfo', static fn (): ObjectType => PaginatorTypes::info());
        $paginator = $this->register($context, $node1->name().'Paginator', static fn (): ObjectType => PaginatorTypes::paginator($node1, $info));

        return FieldDefinition::make(
            $field->getName(),
            Type::nonNull($paginator),
            static function ($root, array $args) use ($modelClass, $default, $max): array {
                $first = min(is_int($args['first'] ?? null) ? $args['first'] : $default, $max);
                $page = is_int($args['page'] ?? null) ? $args['page'] : 1;
                $paginator = $modelClass::query()->paginate(max($first, 1), ['*'], 'page', max($page, 1));

                return [
                    'data' => $paginator->items(),
                    'paginatorInfo' => [
                        'count' => $paginator->count(),
                        'currentPage' => $paginator->currentPage(),
                        'firstItem' => $paginator->firstItem(),
                        'hasMorePages' => $paginator->hasMorePages(),
                        'lastItem' => $paginator->lastItem(),
                        'lastPage' => $paginator->lastPage(),
                        'perPage' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ];
            },
            [...array_values($field->args()), Argument::make('first', Type::int()), Argument::make('page', Type::int())],
            $field->description(),
        );
    }

    /**
     * @param  callable(): ObjectType  $factory
     */
    private function register(SchemaBuildContext $context, string $name, callable $factory): ObjectType
    {
        $existing = $context->getType($name);
        if ($existing instanceof ObjectType) {
            return $existing;
        }

        $type = $factory();
        $context->registerType($type);

        return $type;
    }
}
