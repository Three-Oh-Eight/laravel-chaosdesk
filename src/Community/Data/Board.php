<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

use ThreeOhEight\ChaosDesk\Community\ThreadKind;

/**
 * A community board.
 *
 * `charter` and `member` are only present when the board was fetched for a
 * member (CommunityMemberClient::board()); the site-level board list leaves
 * them null.
 */
final readonly class Board
{
    /**
     * @param  list<string>  $allowedKinds
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        public array $allowedKinds,
        public int $charterVersion,
        public ?Charter $charter = null,
        public ?MemberStatus $member = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $charter = Values::nested($data, 'charter');
        $member = Values::nested($data, 'member');

        return new self(
            Values::string($data, 'slug'),
            Values::string($data, 'name'),
            Values::nullableString($data, 'description'),
            Values::strings($data, 'allowed_kinds'),
            Values::int($data, 'charter_version', 1),
            $charter === null ? null : Charter::fromArray($charter),
            $member === null ? null : MemberStatus::fromArray($member),
        );
    }

    /**
     * Whether members may start a thread of this kind on the board.
     */
    public function allows(ThreadKind|string $kind): bool
    {
        $kind = $kind instanceof ThreadKind ? $kind->value : $kind;

        return in_array($kind, $this->allowedKinds, true);
    }

    /**
     * Whether the member has to accept the current charter before posting or voting.
     *
     * False when the board was not fetched for a member.
     */
    public function needsCharterAcceptance(): bool
    {
        return $this->charter !== null && ! $this->charter->accepted;
    }

    /**
     * Whether the acting member is blocked from writing on the board.
     */
    public function memberIsBlocked(): bool
    {
        return $this->member !== null && $this->member->isBlocked;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'allowed_kinds' => $this->allowedKinds,
            'charter_version' => $this->charterVersion,
            'charter' => $this->charter?->toArray(),
            'member' => $this->member?->toArray(),
        ];
    }
}
