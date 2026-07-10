<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\SchemaFirst;

use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;

/**
 * Build-time behaviour for a custom SDL directive applied to a field definition
 * (e.g. wrapping its resolver). Applied by {@see AstToSchema} during schema build.
 */
interface SchemaDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node): FieldDefinition;
}
