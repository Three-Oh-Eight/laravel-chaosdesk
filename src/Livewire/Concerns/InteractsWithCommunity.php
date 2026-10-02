<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Livewire\Concerns;

use Closure;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Community\CommunityMemberClient;
use ThreeOhEight\ChaosDesk\Community\Data\Board;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Exceptions\CommunityException;
use ThreeOhEight\ChaosDesk\Support\CommunityText;

/**
 * What every community component shares: the board, the site, acting as the
 * signed-in user, and turning refusals into messages.
 *
 * `board` and `site` are locked: the host routed the member to this board,
 * and a tampered request must not move them to another one.
 */
trait InteractsWithCommunity
{
    /**
     * The slug of the board the host routed the member to.
     */
    #[Locked]
    public string $board = '';

    /**
     * The configured site name (`chaosdesk.sites.{name}`); null for the default site.
     */
    #[Locked]
    public ?string $site = null;

    /**
     * Rendered inside the board component, which then handles navigation.
     */
    #[Locked]
    public bool $embedded = false;

    public ?string $error = null;

    /**
     * The board as the acting member sees it: the charter state and whether
     * they are blocked. Null for a guest or when ChaosDesk refused.
     */
    public function getCommunityBoardProperty(): ?Board
    {
        $community = $this->community();

        if ($community === null) {
            return null;
        }

        try {
            return $community->board($this->board);
        } catch (ChaosDeskException $e) {
            $this->error = CommunityText::error($e);

            return null;
        }
    }

    /**
     * Whether the member may write: charter accepted and not blocked.
     */
    public function getCanWriteProperty(): bool
    {
        $board = $this->communityBoard;

        return $board !== null && ! $board->needsCharterAcceptance() && ! $board->memberIsBlocked();
    }

    #[On('chaosdesk-community-charter-accepted')]
    public function onCharterAccepted(string $board = ''): void
    {
        if ($board === $this->board) {
            $this->error = null;
            unset($this->communityBoard);
        }
    }

    /**
     * Back to the board list when rendered inside the board component.
     */
    public function back(): void
    {
        $this->dispatch('chaosdesk-community-close', board: $this->board);
    }

    /**
     * Whether a thread or poll id from the browser has the shape of a ulid.
     * Anything else never reaches the API, so a tampered id cannot form a
     * path the client refuses.
     */
    protected static function isCommunityId(string $id): bool
    {
        return preg_match('/^[0-9A-Za-z]{26}$/', $id) === 1;
    }

    /**
     * Show that the thread or poll asked for does not exist.
     */
    protected function notFound(): void
    {
        $this->error = (string) __('chaosdesk::community.errors.not_found');
    }

    /**
     * Read from the API as the member; null for a guest or after showing
     * the refusal.
     *
     * @template TResult
     *
     * @param  Closure(CommunityMemberClient): TResult  $read
     * @return TResult|null
     */
    protected function readCommunity(Closure $read): mixed
    {
        $community = $this->community();

        if ($community === null) {
            return null;
        }

        try {
            return $read($community);
        } catch (ChaosDeskException $e) {
            $this->fail($e);

            return null;
        }
    }

    /**
     * Upvote a thread, or withdraw the member's upvote when they already
     * voted. Returns whether ChaosDesk took it.
     *
     * @param  Closure(): bool  $hasVoted
     */
    protected function toggleVoteOn(string $thread, Closure $hasVoted): bool
    {
        $community = $this->communityForAction();

        if ($community === null) {
            return false;
        }

        try {
            if ($hasVoted()) {
                $community->unvote($this->board, $thread);
            } else {
                $community->vote($this->board, $thread);
            }
        } catch (ChaosDeskException $e) {
            $this->fail($e);

            return false;
        }

        return true;
    }

    /**
     * The Community API acting as the signed-in user, on the configured site.
     */
    protected function community(): ?CommunityMemberClient
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        $chaosDesk = app(ChaosDesk::class);

        if ($this->site !== null && $this->site !== '') {
            $chaosDesk = $chaosDesk->forSite($this->site);
        }

        return $chaosDesk->community()->as($user);
    }

    /**
     * The client for an action, or null after flagging a guest.
     */
    protected function communityForAction(): ?CommunityMemberClient
    {
        $this->error = null;
        $community = $this->community();

        if ($community === null) {
            $this->error = (string) __('chaosdesk::community.guest');
        }

        return $community;
    }

    /**
     * Show a refusal as a message; when the charter is the reason, refetch the
     * board so the component renders the charter instead.
     */
    protected function fail(ChaosDeskException $e): void
    {
        $this->error = CommunityText::error($e);

        if ($e instanceof CommunityException && ($e->requiresCharterAcceptance() || $e->isMemberBlocked())) {
            unset($this->communityBoard);
        }
    }

    /**
     * An idempotency key that stays the same for a retry of the same submit
     * and changes with its content, since ChaosDesk refuses a reused key
     * with a different body.
     *
     * @param  array<int|string, mixed>  $payload
     */
    protected function idempotencyKey(string $formKey, array $payload): string
    {
        return $formKey.':'.substr(hash('sha256', (string) json_encode($payload)), 0, 16);
    }
}
