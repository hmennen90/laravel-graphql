<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Language\AST\EnumValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;

/** A GraphQL enum type. */
final class EnumType extends Type implements NamedType, OutputType, InputType, LeafType
{
    /** @var array<string, EnumValueDefinition> */
    private readonly array $values;

    /**
     * @param  array<int|string, EnumValueDefinition>  $values
     */
    public function __construct(
        private readonly string $name,
        array $values,
        private readonly ?string $description = null,
    ) {
        $keyed = [];
        foreach ($values as $value) {
            $keyed[$value->getName()] = $value;
        }
        $this->values = $keyed;
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
     * @return array<string, EnumValueDefinition>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function serialize(mixed $value): string
    {
        foreach ($this->values as $definition) {
            if ($definition->getValue() === $value) {
                return $definition->getName();
            }
        }

        throw new CoercionError(sprintf('Enum "%s" cannot represent value: %s', $this->name, get_debug_type($value)));
    }

    public function parseValue(mixed $value): mixed
    {
        if (is_string($value) && isset($this->values[$value])) {
            return $this->values[$value]->getValue();
        }

        throw new CoercionError(sprintf('Enum "%s" cannot represent non-enum value.', $this->name));
    }

    public function parseLiteral(ValueNode $node, array $variables): mixed
    {
        if ($node instanceof EnumValueNode && isset($this->values[$node->value])) {
            return $this->values[$node->value]->getValue();
        }

        throw new CoercionError(sprintf('Enum "%s" cannot represent non-enum value.', $this->name));
    }

    public function toString(): string
    {
        return $this->name;
    }
}
