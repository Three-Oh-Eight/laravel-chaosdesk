<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

use Carbon\CarbonImmutable;

/**
 * The charter version a member accepted, and when.
 */
final readonly class CharterAcceptance
{
    public function __construct(
        public int $version,
        public ?CarbonImmutable $acceptedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::int($data, 'version', 1),
            Values::date($data, 'accepted_at'),
        );
    }

    /**
     * @return array{version: int, accepted: bool, accepted_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'accepted' => true,
            'accepted_at' => Values::iso($this->acceptedAt),
        ];
    }
}
