<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use ThreeOhEight\ChaosDesk\Community\CommunityUrls;
use ThreeOhEight\ChaosDesk\Community\Data\Board;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Livewire\Concerns\InteractsWithCommunity;

/**
 * The form that starts a thread, limited to the kinds the board allows.
 *
 * The idempotency key is generated once per form and kept across retries of
 * the same submit, so a double click or a retry after a timeout replays the
 * first answer instead of posting the thread twice.
 *
 * After posting it dispatches `chaosdesk-community-thread-created` with the
 * board and the thread ulid, then goes to the thread url when one is
 * configured; inside the board component the board opens the thread.
 *
 * @property-read Board|null $communityBoard
 * @property-read bool $canWrite
 */
class CommunityNewThread extends Component
{
    use InteractsWithCommunity;

    public string $kind = '';

    public string $title = '';

    public string $body = '';

    #[Locked]
    public string $formKey = '';

    /**
     * The ulid of the thread just posted, when there is no thread url to go to.
     */
    public ?string $createdThread = null;

    public function mount(): void
    {
        $this->formKey = (string) Str::uuid();
        $this->kind = $this->communityBoard?->allowedKinds[0] ?? '';
    }

    public function submit(): void
    {
        $board = $this->communityBoard;
        $allowed = $board === null ? [] : $board->allowedKinds;

        $this->validate([
            'kind' => ['required', 'string', Rule::in($allowed)],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $community = $this->communityForAction();

        if ($community === null) {
            return;
        }

        try {
            $thread = $community->createThread(
                $this->board,
                $this->kind,
                $this->title,
                $this->body,
                $this->idempotencyKey($this->formKey, [$this->kind, $this->title, $this->body]),
            );
        } catch (ChaosDeskException $e) {
            if ($e->isValidationError() && $this->addServerErrors($e->errors)) {
                return;
            }

            $this->fail($e);

            return;
        }

        $this->reset(['title', 'body']);
        $this->formKey = (string) Str::uuid();

        $this->dispatch('chaosdesk-community-thread-created', board: $this->board, thread: $thread->ulid);

        if ($this->embedded) {
            return;
        }

        $url = CommunityUrls::thread($this->board, $thread->ulid, $this->site);

        if ($url !== null) {
            $this->redirect($url);

            return;
        }

        $this->createdThread = $thread->ulid;
    }

    public function startOver(): void
    {
        $this->createdThread = null;
    }

    public function render(): View
    {
        return view('chaosdesk::livewire.community.new-thread');
    }

    /**
     * Put the API's validation errors on the matching fields.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function addServerErrors(array $errors): bool
    {
        $added = false;

        foreach (['kind', 'title', 'body'] as $field) {
            $message = $errors[$field][0] ?? null;

            if (is_string($message)) {
                $this->addError($field, $message);
                $added = true;
            }
        }

        return $added;
    }
}
