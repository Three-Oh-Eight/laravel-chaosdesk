<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

/**
 * The acting member as ChaosDesk sees them on a board.
 *
 * A blocked member can still read; every write answers `member_blocked`.
 */
final readonly class MemberStatus
{
    public function __construct(
        public ?string $name,
        public bool $isBlocked,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::nullableString($data, 'name'),
            Values::bool($data, 'is_blocked'),
        );
    }

    /**
     * @return array{name: string|null, is_blocked: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'is_blocked' => $this->isBlocked,
        ];
    }
}
