<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use ThreeOhEight\ChaosDesk\Community\CommunityMemberClient;
use ThreeOhEight\ChaosDesk\Community\Data\Board;
use ThreeOhEight\ChaosDesk\Community\Data\Page;
use ThreeOhEight\ChaosDesk\Community\Data\Poll;
use ThreeOhEight\ChaosDesk\Community\Data\PollOption;
use ThreeOhEight\ChaosDesk\Community\Data\PollResults;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Livewire\Concerns\InteractsWithCommunity;

/**
 * The board's polls: open ones take an answer (one option or several), closed
 * ones show their results. ChaosDesk never reveals a tally while a poll runs.
 *
 * @property-read Board|null $communityBoard
 * @property-read bool $canWrite
 * @property-read Page<Poll>|null $polls
 * @property-read array<string, PollResults> $results
 */
class CommunityPolls extends Component
{
    use InteractsWithCommunity;

    /**
     * The member's picks per poll ulid: an option ulid for a single-choice
     * poll, a list of them for a multiple-choice one.
     *
     * @var array<string, string|list<string>>
     */
    public array $answers = [];

    /**
     * The poll whose answer was just saved.
     */
    public ?string $savedPoll = null;

    public function mount(): void
    {
        foreach ($this->polls ?? [] as $poll) {
            $this->rememberAnswer($poll);
        }
    }

    /**
     * The polls that have opened, newest first.
     *
     * @return Page<Poll>|null
     */
    public function getPollsProperty(): ?Page
    {
        return $this->readCommunity(fn (CommunityMemberClient $community): Page => $community->polls($this->board, 1, 50));
    }

    /**
     * The results of every closed poll on the page, keyed by poll ulid. A
     * poll whose results cannot be fetched is left out.
     *
     * The list endpoint carries no tally, so each closed poll costs a call.
     * A closed tally is the same for every member of the board, so it is
     * cached for `chaosdesk.community.results_ttl` seconds: later renders
     * and other members do not fetch it again. A refusal is never cached.
     *
     * @return array<string, PollResults>
     */
    public function getResultsProperty(): array
    {
        $community = $this->community();

        if ($community === null) {
            return [];
        }

        $ttl = $this->resultsTtl();
        $results = [];

        foreach ($this->polls ?? [] as $poll) {
            if (! $poll->isClosed) {
                continue;
            }

            $key = $this->resultsCacheKey($poll->ulid);
            $cached = $ttl > 0 ? Cache::get($key) : null;

            if (is_array($cached)) {
                $results[$poll->ulid] = PollResults::fromArray($cached);

                continue;
            }

            try {
                $results[$poll->ulid] = $community->pollResults($this->board, $poll->ulid);
            } catch (ChaosDeskException) {
                continue;
            }

            if ($ttl > 0) {
                Cache::put($key, $results[$poll->ulid]->toArray(), $ttl);
            }
        }

        return $results;
    }

    public function respond(string $poll): void
    {
        $this->savedPoll = null;
        $this->resetErrorBag("answers.{$poll}");

        $current = $this->findPoll($poll);

        if ($current === null) {
            $this->notFound();

            return;
        }

        $options = $this->picked($current);

        if ($options === []) {
            $this->addError("answers.{$poll}", (string) __('chaosdesk::community.polls.choose'));

            return;
        }

        $community = $this->communityForAction();

        if ($community === null) {
            return;
        }

        try {
            $answered = $community->respond($this->board, $current->ulid, $options);
        } catch (ChaosDeskException $e) {
            $this->fail($e);
            unset($this->polls);

            return;
        }

        $this->rememberAnswer($answered);
        $this->savedPoll = $current->ulid;
        unset($this->polls);
    }

    public function render(): View
    {
        return view('chaosdesk::livewire.community.polls');
    }

    /**
     * The picked options that belong to the poll, at most one on a single-choice poll.
     *
     * @return list<string>
     */
    protected function picked(Poll $poll): array
    {
        $valid = array_map(fn (PollOption $option): string => $option->ulid, $poll->options);
        $answer = $this->answers[$poll->ulid] ?? [];
        $picked = array_values(array_unique(array_filter(
            is_array($answer) ? $answer : [$answer],
            fn (mixed $ulid): bool => is_string($ulid) && in_array($ulid, $valid, true),
        )));

        return $poll->isMultipleChoice ? $picked : array_slice($picked, 0, 1);
    }

    protected function rememberAnswer(Poll $poll): void
    {
        $this->answers[$poll->ulid] = $poll->isMultipleChoice ? $poll->myOptions : ($poll->myOptions[0] ?? '');
    }

    protected function resultsTtl(): int
    {
        $ttl = config('chaosdesk.community.results_ttl', 600);

        return is_numeric($ttl) ? max(0, (int) $ttl) : 600;
    }

    /**
     * Per site, board and poll: the same ulid never collides across sites.
     */
    protected function resultsCacheKey(string $poll): string
    {
        return 'chaosdesk:community:poll-results:'.hash('sha256', ($this->site ?: 'default')."\0".$this->board."\0".$poll);
    }

    protected function findPoll(string $ulid): ?Poll
    {
        $polls = $this->polls;

        return array_find($polls === null ? [] : $polls->items, fn (Poll $poll): bool => $poll->ulid === $ulid);
    }
}
