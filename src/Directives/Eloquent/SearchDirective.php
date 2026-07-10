<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Laravel\Scout\Builder;

/**
 * `@search(by:)` — full-text search via Laravel Scout. The search term is read
 * from the field argument named by `by:` (default `search`). Requires the model
 * to use the `Laravel\Scout\Searchable` trait.
 */
final readonly class SearchDirective extends EloquentDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition
    {
        $modelClass = $this->modelClass($node, $field);
        $argument = $this->stringArg($node, 'by') ?? 'search';

        return FieldDefinition::make(
            $field->getName(),
            $field->getType(),
            static function ($root, array $args) use ($modelClass, $argument): array {
                $term = $args[$argument] ?? '';
                $search = [$modelClass, 'search'];
                $builder = is_callable($search) ? $search(is_string($term) ? $term : '') : null;

                return $builder instanceof Builder ? $builder->get()->all() : [];
            },
            array_values($field->args()),
            $field->description(),
            $field->deprecationReason(),
        );
    }
}
