<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Closure;
use LogicException;

/** A GraphQL input object type. */
final class InputObjectType extends Type implements NamedType, InputType
{
    /** @var array<string, InputObjectField>|null */
    private ?array $resolvedFields = null;

    /**
     * @param Closure():(array<int|string, InputObjectField>)|array<int|string, InputObjectField> $fieldsConfig
     */
    public function __construct(private readonly string $name, private readonly Closure|array $fieldsConfig, private readonly ?string $description = null, private readonly bool $isOneOf = false)
    {
    }

    public function isOneOf(): bool
    {
        return $this->isOneOf;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, InputObjectField>
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

    public function getField(string $name): InputObjectField
    {
        $fields = $this->fields();
        if (! isset($fields[$name])) {
            throw new LogicException(sprintf('Unknown input field "%s" on type "%s".', $name, $this->name));
        }

        return $fields[$name];
    }

    public function toString(): string
    {
        return $this->name;
    }
}
