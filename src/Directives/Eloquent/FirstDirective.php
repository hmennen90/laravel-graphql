<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

/**
 * `@first` — resolves a field to the first model matching the provided field
 * arguments. Same resolution as {@see FindDirective}; kept as a distinct
 * directive name for schema readability.
 */
final readonly class FirstDirective extends FindDirective
{
}
