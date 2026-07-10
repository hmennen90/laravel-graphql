<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Error;

use Exception;
use Hmennen90\GraphQL\Engine\Language\Source;
use Throwable;

/**
 * The base error type for every failure in the engine. Carries enough context
 * to render the canonical GraphQL error shape ({message, locations, path, extensions}).
 */
class GraphQLError extends Exception
{
    /**
     * @param  array<int, object>  $nodes  AST nodes associated with the error.
     * @param  array<int, int>  $positions  Byte offsets into $source.
     * @param  array<int, string|int>  $path  Response path where the error occurred.
     * @param  array<string, mixed>  $extensions
     */
    public function __construct(
        string $message,
        private readonly array $nodes = [],
        private readonly ?Source $source = null,
        private readonly array $positions = [],
        private readonly array $path = [],
        ?Throwable $previous = null,
        private readonly array $extensions = [],
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array<int, string|int>
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @return array<int, object>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Resolved 1-based source locations, computed from positions or node locations.
     *
     * @return array<int, array{line: int, column: int}>
     */
    public function getLocations(): array
    {
        $locations = [];

        if ($this->source !== null) {
            foreach ($this->positions as $position) {
                $locations[] = $this->source->getLocation($position)->toArray();
            }
        }

        foreach ($this->nodes as $node) {
            if (! isset($node->loc) || $node->loc === null) {
                continue;
            }
            $loc = $node->loc;
            if (isset($loc->source, $loc->start)) {
                $locations[] = $loc->source->getLocation($loc->start)->toArray();
            }
        }

        return $locations;
    }

    /**
     * The canonical GraphQL error array. Optional keys are omitted when empty.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['message' => $this->getMessage()];

        $locations = $this->getLocations();
        if ($locations !== []) {
            $result['locations'] = $locations;
        }

        if ($this->path !== []) {
            $result['path'] = $this->path;
        }

        if ($this->extensions !== []) {
            $result['extensions'] = $this->extensions;
        }

        return $result;
    }
}
