<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

/**
 * The default field resolver: reads array keys, object properties or getter
 * methods matching the field name.
 */
final class DefaultFieldResolver
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $source, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $field = $info->fieldName;

        if (is_array($source)) {
            return $source[$field] ?? null;
        }

        if (is_object($source)) {
            if (isset($source->{$field})) {
                return $source->{$field};
            }
            if (method_exists($source, $field)) {
                return $source->{$field}($args, $context, $info);
            }
        }

        return null;
    }
}
