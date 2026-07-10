<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Federation;

use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\SchemaAnnotations;

/**
 * Renders Apollo Federation v2 directive annotations onto the subgraph SDL: the
 * `@link` schema header, `@key` on entity types and `@shareable`/`@external`/
 * `@requires`/`@provides` on fields — derived from the {@see Federation::subgraph()}
 * entity configuration.
 */
final readonly class FederationAnnotations implements SchemaAnnotations
{
    private const string SPEC_URL = 'https://specs.apollo.dev/federation/v2.3';

    /**
     * @param  array<string, list<string>>  $keys        type => list of @key field selections
     * @param  array<string, list<string>>  $shareable   type => @shareable field names
     * @param  array<string, list<string>>  $external    type => @external field names
     * @param  array<string, array<string, string>>  $requires  type => (field => fields selection)
     * @param  array<string, array<string, string>>  $provides  type => (field => fields selection)
     */
    public function __construct(
        private array $keys,
        private array $shareable,
        private array $external,
        private array $requires,
        private array $provides,
    ) {
    }

    /**
     * @param  array<string, array<string, mixed>>  $entities
     */
    public static function fromConfig(array $entities): self
    {
        $keys = $shareable = $external = $requires = $provides = [];

        foreach ($entities as $type => $config) {
            $keys[$type] = self::stringList($config['keys'] ?? $config['key'] ?? 'id');
            $shareable[$type] = self::stringList($config['shareable'] ?? []);
            $external[$type] = self::stringList($config['external'] ?? []);
            $requires[$type] = self::stringMap($config['requires'] ?? []);
            $provides[$type] = self::stringMap($config['provides'] ?? []);
        }

        return new self($keys, $shareable, $external, $requires, $provides);
    }

    /**
     * Derive the annotations from the federation directives written in the SDL itself
     * (`type User @key(fields: "id")`, field `@shareable`/`@external`/`@requires`/
     * `@provides`) — so keys need not be duplicated in config.
     */
    public static function fromSdl(string $sdl): self
    {
        $keys = $shareable = $external = $requires = $provides = [];

        foreach (Parser::parse($sdl)->definitions as $definition) {
            if (! $definition instanceof ObjectTypeDefinitionNode) {
                continue;
            }
            $type = $definition->name;

            foreach ($definition->directives as $directive) {
                if ($directive->name === 'key' && ($fields = self::directiveArg($directive, 'fields')) !== null) {
                    $keys[$type][] = $fields;
                }
            }

            foreach ($definition->fields as $field) {
                foreach ($field->directives as $directive) {
                    match ($directive->name) {
                        'shareable' => $shareable[$type][] = $field->name,
                        'external' => $external[$type][] = $field->name,
                        'requires' => $requires[$type][$field->name] = self::directiveArg($directive, 'fields') ?? '',
                        'provides' => $provides[$type][$field->name] = self::directiveArg($directive, 'fields') ?? '',
                        default => null,
                    };
                }
            }
        }

        return new self($keys, $shareable, $external, $requires, $provides);
    }

    private static function directiveArg(DirectiveNode $node, string $name): ?string
    {
        foreach ($node->arguments as $argument) {
            if ($argument->name === $name && $argument->value instanceof StringValueNode) {
                return $argument->value->value;
            }
        }

        return null;
    }

    #[\Override]
    public function header(): string
    {
        $imports = ['@key'];
        if (self::any($this->shareable)) {
            $imports[] = '@shareable';
        }
        if (self::any($this->external)) {
            $imports[] = '@external';
        }
        if (self::anyMap($this->requires)) {
            $imports[] = '@requires';
        }
        if (self::anyMap($this->provides)) {
            $imports[] = '@provides';
        }

        $importList = implode(', ', array_map(static fn (string $i): string => '"'.$i.'"', $imports));

        return "extend schema\n  @link(url: \"".self::SPEC_URL."\", import: [".$importList."])";
    }

    #[\Override]
    public function typeAnnotations(string $type): string
    {
        $out = '';
        foreach ($this->keys[$type] ?? [] as $fields) {
            $out .= ' @key(fields: "'.$fields.'")';
        }

        return $out;
    }

    #[\Override]
    public function fieldAnnotations(string $type, string $field): string
    {
        $out = '';
        if (in_array($field, $this->shareable[$type] ?? [], true)) {
            $out .= ' @shareable';
        }
        if (in_array($field, $this->external[$type] ?? [], true)) {
            $out .= ' @external';
        }
        if (isset($this->requires[$type][$field])) {
            $out .= ' @requires(fields: "'.$this->requires[$type][$field].'")';
        }
        if (isset($this->provides[$type][$field])) {
            $out .= ' @provides(fields: "'.$this->provides[$type][$field].'")';
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $fields) {
            if (is_string($key) && is_string($fields)) {
                $out[$key] = $fields;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, list<string>>  $map
     */
    private static function any(array $map): bool
    {
        return array_any($map, static fn (array $items): bool => $items !== []);
    }

    /**
     * @param  array<string, array<string, string>>  $map
     */
    private static function anyMap(array $map): bool
    {
        return array_any($map, static fn (array $items): bool => $items !== []);
    }
}
