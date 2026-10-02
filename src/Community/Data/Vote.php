<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

/**
 * A proposal's vote tally after the member voted or withdrew their vote.
 */
final readonly class Vote
{
    public function __construct(
        public string $threadUlid,
        public int $votesCount,
        public bool $hasVoted,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'thread_ulid'),
            Values::int($data, 'votes_count'),
            Values::bool($data, 'has_voted'),
        );
    }

    /**
     * @return array{thread_ulid: string, votes_count: int, has_voted: bool}
     */
    public function toArray(): array
    {
        return [
            'thread_ulid' => $this->threadUlid,
            'votes_count' => $this->votesCount,
            'has_voted' => $this->hasVoted,
        ];
    }
}
