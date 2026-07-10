<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Closure;
use LogicException;

/**
 * Shared lazy field resolution for object and interface types. Field config may
 * be a closure so recursive/cyclic type graphs can be defined in any order.
 */
trait ResolvesFields
{
    /** @var array<string, FieldDefinition>|null */
    private ?array $resolvedFields = null;

    /** @var Closure(): array<int|string, FieldDefinition>|array<int|string, FieldDefinition> */
    private Closure|array $fieldsConfig = [];

    /**
     * @return array<string, FieldDefinition>
     */
    public function fields(): array
    {
        if ($this->resolvedFields !== null) {
            return $this->resolvedFields;
        }

        $raw = $this->fieldsConfig instanceof Closure ? ($this->fieldsConfig)() : $this->fieldsConfig;

        $fields = [];
        foreach ($raw as $field) {
            $fields[$field->getName()] = $field;
        }

        return $this->resolvedFields = $fields;
    }

    public function hasField(string $name): bool
    {
        return isset($this->fields()[$name]);
    }

    public function getField(string $name): FieldDefinition
    {
        $fields = $this->fields();
        if (! isset($fields[$name])) {
            throw new LogicException(sprintf('Unknown field "%s" on type "%s".', $name, $this->name()));
        }

        return $fields[$name];
    }

    abstract public function name(): string;
}
