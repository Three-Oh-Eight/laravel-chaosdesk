<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

/**
 * A board's charter and whether the acting member accepted its current version.
 */
final readonly class Charter
{
    public function __construct(
        public ?string $markdown,
        public int $version,
        public bool $accepted,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::nullableString($data, 'markdown'),
            Values::int($data, 'version', 1),
            Values::bool($data, 'accepted'),
        );
    }

    /**
     * @return array{markdown: string|null, version: int, accepted: bool}
     */
    public function toArray(): array
    {
        return [
            'markdown' => $this->markdown,
            'version' => $this->version,
            'accepted' => $this->accepted,
        ];
    }
}
