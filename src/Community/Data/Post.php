<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

use Carbon\CarbonImmutable;

/**
 * A reply on a thread, by a member or by an agent of the team.
 *
 * Authors appear by display name only. `isMine` is relative to the member
 * the client acts as.
 */
final readonly class Post
{
    public function __construct(
        public string $ulid,
        public string $body,
        public bool $isOfficial,
        public bool $isAgent,
        public bool $isMine,
        public ?string $authorName,
        public ?CarbonImmutable $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $author = Values::nested($data, 'author') ?? [];

        return new self(
            Values::string($data, 'ulid'),
            Values::string($data, 'body'),
            Values::bool($data, 'is_official'),
            Values::bool($data, 'is_agent'),
            Values::bool($data, 'is_mine'),
            Values::nullableString($author, 'name'),
            Values::date($data, 'created_at'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ulid' => $this->ulid,
            'body' => $this->body,
            'is_official' => $this->isOfficial,
            'is_agent' => $this->isAgent,
            'is_mine' => $this->isMine,
            'author' => ['name' => $this->authorName],
            'created_at' => Values::iso($this->createdAt),
        ];
    }
}
