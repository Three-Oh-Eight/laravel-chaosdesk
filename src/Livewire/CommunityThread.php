<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use ThreeOhEight\ChaosDesk\Community\CommunityMemberClient;
use ThreeOhEight\ChaosDesk\Community\Data\Board;
use ThreeOhEight\ChaosDesk\Community\Data\Thread;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Livewire\Concerns\InteractsWithCommunity;

/**
 * One thread with its replies, the reply form and the upvote on a proposal.
 *
 * Official team replies carry a badge; the status shows with the decline
 * reason. A locked thread reads but takes no replies; a hidden thread is
 * not found.
 *
 * @property-read Board|null $communityBoard
 * @property-read bool $canWrite
 * @property-read Thread|null $detail
 */
class CommunityThread extends Component
{
    use InteractsWithCommunity;

    /**
     * The ulid of the thread on the board.
     */
    #[Locked]
    public string $thread = '';

    public string $reply = '';

    /**
     * Generated once per reply form, so a retried submit replays rather than
     * posts twice; renewed after every posted reply.
     */
    #[Locked]
    public string $formKey = '';

    /**
     * A thread id that is not a ulid (a mistyped or crafted url) is not found.
     */
    public function mount(): void
    {
        abort_unless(self::isCommunityId($this->thread), 404);

        $this->formKey = (string) Str::uuid();
    }

    /**
     * The thread with its visible replies, or null when it cannot be shown.
     */
    public function getDetailProperty(): ?Thread
    {
        return $this->readCommunity(fn (CommunityMemberClient $community): Thread => $community->thread($this->board, $this->thread));
    }

    public function toggleVote(): void
    {
        if ($this->toggleVoteOn($this->thread, fn (): bool => $this->detail?->hasVoted === true)) {
            unset($this->detail);
        }
    }

    public function sendReply(): void
    {
        $this->validate(['reply' => 'required|string|max:10000']);

        $community = $this->communityForAction();

        if ($community === null) {
            return;
        }

        try {
            $community->reply(
                $this->board,
                $this->thread,
                $this->reply,
                $this->idempotencyKey($this->formKey, [$this->thread, $this->reply]),
            );
        } catch (ChaosDeskException $e) {
            if ($e->isValidationError() && isset($e->errors['body'][0])) {
                $this->addError('reply', $e->errors['body'][0]);

                return;
            }

            $this->fail($e);

            return;
        }

        $this->reply = '';
        $this->formKey = (string) Str::uuid();
        unset($this->detail);
    }

    public function render(): View
    {
        return view('chaosdesk::livewire.community.thread');
    }
}
