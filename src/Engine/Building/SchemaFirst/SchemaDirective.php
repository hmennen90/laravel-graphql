<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\SchemaFirst;

use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;

/**
 * Build-time behaviour for a custom SDL/attribute directive applied to a field
 * definition: it may rewrite the field (resolver, type, arguments) and register
 * additional generated types via the {@see SchemaBuildContext}.
 */
interface SchemaDirective
{
    public function applyToField(FieldDefinition $field, DirectiveNode $node, SchemaBuildContext $context): FieldDefinition;
}
