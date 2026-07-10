<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions;

/** A registered subscription: the client channel plus the stored operation. */
final readonly class Subscriber
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public string $channel,
        public string $topic,
        public string $query,
        public array $variables,
        public ?string $operationName,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'topic' => $this->topic,
            'query' => $this->query,
            'variables' => $this->variables,
            'operationName' => $this->operationName,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $variables = [];
        if (is_array($data['variables'] ?? null)) {
            foreach ($data['variables'] as $key => $value) {
                $variables[(string) $key] = $value;
            }
        }

        $operationName = $data['operationName'] ?? null;

        return new self(
            self::asString($data['channel'] ?? null),
            self::asString($data['topic'] ?? null),
            self::asString($data['query'] ?? null),
            $variables,
            is_string($operationName) ? $operationName : null,
        );
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
