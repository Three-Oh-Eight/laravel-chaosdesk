<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use ThreeOhEight\ChaosDesk\Community\CommunityMemberClient;
use ThreeOhEight\ChaosDesk\Community\Data\Board;
use ThreeOhEight\ChaosDesk\Community\Data\Page;
use ThreeOhEight\ChaosDesk\Community\Data\Poll;
use ThreeOhEight\ChaosDesk\Community\Data\Thread;
use ThreeOhEight\ChaosDesk\Community\ThreadKind;
use ThreeOhEight\ChaosDesk\Community\ThreadStatus;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Livewire\Concerns\InteractsWithCommunity;

/**
 * A community board: its threads with filters, sorting and paging, upvotes on
 * proposals, and a summary of the open polls.
 *
 * Until the member accepted the board's charter it renders the charter. A
 * thread, the new-thread form and the polls open through the urls your
 * application configures (see CommunityUrls); without one they show inline.
 *
 * @property-read Board|null $communityBoard
 * @property-read bool $canWrite
 * @property-read Page<Thread>|null $threads
 * @property-read list<Poll> $openPolls
 */
class CommunityBoard extends Component
{
    use InteractsWithCommunity;

    public const SORTS = ['activity', 'votes', 'newest'];

    public const SCREENS = ['list', 'thread', 'new', 'polls'];

    public string $kind = '';

    public string $status = '';

    public string $sort = 'activity';

    public int $currentPage = 1;

    /**
     * What the board shows inline: the list, a thread, the form or the polls.
     */
    public string $screen = 'list';

    /**
     * The ulid of the thread shown inline; only showThread() sets it.
     */
    #[Locked]
    public ?string $openThread = null;

    /**
     * One page of the board's threads under the current filters.
     *
     * @return Page<Thread>|null
     */
    public function getThreadsProperty(): ?Page
    {
        return $this->readCommunity(fn (CommunityMemberClient $community): Page => $community->threads(
            $this->board,
            $this->filters(),
            max(1, $this->currentPage),
            $this->perPage(),
        ));
    }

    /**
     * The polls that are open right now, for the summary above the list.
     *
     * A summary is not worth an error: a failure leaves it out.
     *
     * @return list<Poll>
     */
    public function getOpenPollsProperty(): array
    {
        $community = $this->community();

        if ($community === null) {
            return [];
        }

        try {
            $polls = $community->polls($this->board, 1, 10);
        } catch (ChaosDeskException) {
            return [];
        }

        return array_values(array_filter($polls->items, fn (Poll $poll): bool => $poll->isOpen));
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['kind', 'status', 'sort'], true)) {
            $this->currentPage = 1;
        }
    }

    public function previousPage(): void
    {
        $this->currentPage = max(1, $this->currentPage - 1);
    }

    public function nextPage(): void
    {
        $this->currentPage++;
    }

    /**
     * Upvote a proposal, or withdraw the member's upvote when they already voted.
     */
    public function toggleVote(string $thread): void
    {
        if (! self::isCommunityId($thread)) {
            $this->notFound();

            return;
        }

        if ($this->toggleVoteOn($thread, fn (): bool => $this->threadOnPage($thread)?->hasVoted === true)) {
            unset($this->threads);
        }
    }

    public function showThread(string $thread): void
    {
        if (! self::isCommunityId($thread)) {
            $this->notFound();

            return;
        }

        $this->error = null;
        $this->openThread = $thread;
        $this->screen = 'thread';
    }

    public function compose(): void
    {
        $this->error = null;
        $this->screen = 'new';
    }

    public function showPolls(): void
    {
        $this->error = null;
        $this->screen = 'polls';
    }

    public function showList(): void
    {
        $this->error = null;
        $this->openThread = null;
        $this->screen = 'list';
    }

    #[On('chaosdesk-community-close')]
    public function onClose(string $board = ''): void
    {
        if ($board === $this->board) {
            $this->showList();
        }
    }

    #[On('chaosdesk-community-thread-created')]
    public function onThreadCreated(string $board = '', string $thread = ''): void
    {
        if ($board === $this->board && $thread !== '') {
            unset($this->threads);
            $this->showThread($thread);
        }
    }

    public function render(): View
    {
        if (! in_array($this->screen, self::SCREENS, true) || ($this->screen === 'thread' && $this->openThread === null)) {
            $this->screen = 'list';
        }

        return view('chaosdesk::livewire.community.board');
    }

    /**
     * The filters as the API takes them; anything unknown is dropped.
     *
     * @return array{kind: string|null, status: string|null, sort: string}
     */
    protected function filters(): array
    {
        return [
            'kind' => ThreadKind::tryFrom($this->kind)?->value,
            'status' => ThreadStatus::tryFrom($this->status)?->value,
            'sort' => in_array($this->sort, self::SORTS, true) ? $this->sort : 'activity',
        ];
    }

    protected function perPage(): int
    {
        return max(1, min(50, (int) config('chaosdesk.community.per_page', 20)));
    }

    protected function threadOnPage(string $ulid): ?Thread
    {
        $threads = $this->threads;

        return array_find($threads === null ? [] : $threads->items, fn (Thread $thread): bool => $thread->ulid === $ulid);
    }
}
