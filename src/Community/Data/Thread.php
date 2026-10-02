<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

use Carbon\CarbonImmutable;
use ThreeOhEight\ChaosDesk\Community\ThreadKind;
use ThreeOhEight\ChaosDesk\Community\ThreadStatus;

/**
 * A thread on a board: a discussion, a proposal or a bug report.
 *
 * `kind` and `status` hold the raw API values (see ThreadKind and
 * ThreadStatus), so a value a newer ChaosDesk adds never breaks parsing.
 * `isMine` and `hasVoted` are relative to the member the client acts as.
 * `posts` is only filled by CommunityMemberClient::thread().
 */
final readonly class Thread
{
    /**
     * @param  list<Post>  $posts
     */
    public function __construct(
        public string $ulid,
        public string $kind,
        public string $title,
        public string $body,
        public string $status,
        public ?string $declineReason,
        public int $votesCount,
        public int $postsCount,
        public bool $acceptsVotes,
        public bool $isPinned,
        public bool $isLocked,
        public bool $isMine,
        public bool $hasVoted,
        public ?string $authorName,
        public array $posts,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $lastActivityAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $author = Values::nested($data, 'author') ?? [];

        return new self(
            Values::string($data, 'ulid'),
            Values::string($data, 'kind'),
            Values::string($data, 'title'),
            Values::string($data, 'body'),
            Values::string($data, 'status'),
            Values::nullableString($data, 'decline_reason'),
            Values::int($data, 'votes_count'),
            Values::int($data, 'posts_count'),
            Values::bool($data, 'accepts_votes'),
            Values::bool($data, 'is_pinned'),
            Values::bool($data, 'is_locked'),
            Values::bool($data, 'is_mine'),
            Values::bool($data, 'has_voted'),
            Values::nullableString($author, 'name'),
            array_map(Post::fromArray(...), Values::items($data, 'posts')),
            Values::date($data, 'created_at'),
            Values::date($data, 'last_activity_at'),
        );
    }

    public function isKind(ThreadKind|string $kind): bool
    {
        return $this->kind === ($kind instanceof ThreadKind ? $kind->value : $kind);
    }

    public function hasStatus(ThreadStatus|string $status): bool
    {
        return $this->status === ($status instanceof ThreadStatus ? $status->value : $status);
    }

    /**
     * Whether the thread takes replies; a locked thread answers `thread_locked`.
     */
    public function acceptsReplies(): bool
    {
        return ! $this->isLocked;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ulid' => $this->ulid,
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'decline_reason' => $this->declineReason,
            'votes_count' => $this->votesCount,
            'posts_count' => $this->postsCount,
            'accepts_votes' => $this->acceptsVotes,
            'is_pinned' => $this->isPinned,
            'is_locked' => $this->isLocked,
            'is_mine' => $this->isMine,
            'has_voted' => $this->hasVoted,
            'author' => ['name' => $this->authorName],
            'posts' => array_map(fn (Post $post): array => $post->toArray(), $this->posts),
            'created_at' => Values::iso($this->createdAt),
            'last_activity_at' => Values::iso($this->lastActivityAt),
        ];
    }
}
