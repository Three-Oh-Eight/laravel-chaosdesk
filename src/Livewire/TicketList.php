<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Contracts\TicketStore;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Support\Identity;
use ThreeOhEight\ChaosDesk\Support\TicketReference;

/**
 * Lists the signed-in user's tickets and lets them continue a conversation.
 */
class TicketList extends Component
{
    public ?string $openUlid = null;

    #[Validate('required|string|max:10000')]
    public string $reply = '';

    public ?string $error = null;

    /**
     * The user's tickets, from the local reference table.
     */
    /**
     * @return Collection<int, TicketReference>
     */
    public function getTicketsProperty(): Collection
    {
        $externalId = Identity::externalId(Auth::user());

        return $externalId === null
            ? collect()
            : app(TicketStore::class)->forUser($externalId, app(ChaosDesk::class)->site());
    }

    /**
     * The open thread, fetched from ChaosDesk.
     *
     * @return array<string, mixed>|null
     */
    public function getThreadProperty(): ?array
    {
        if ($this->openUlid === null) {
            return null;
        }

        $reference = $this->reference($this->openUlid);

        if ($reference === null) {
            return null;
        }

        try {
            return app(ChaosDesk::class)->ticket($this->openUlid, $reference->accessToken)['data'] ?? null;
        } catch (ChaosDeskException $e) {
            $this->error = $e->getMessage();

            return null;
        }
    }

    public function open(string $ulid): void
    {
        $this->error = null;
        $this->reply = '';
        $this->openUlid = $ulid;
    }

    public function close(): void
    {
        $this->openUlid = null;
        $this->reply = '';
        $this->error = null;
    }

    public function sendReply(ChaosDesk $chaosDesk): void
    {
        $this->validate();

        $reference = $this->openUlid === null ? null : $this->reference($this->openUlid);

        if ($reference === null) {
            $this->error = __('chaosdesk::chaosdesk.tickets.not_found');

            return;
        }

        try {
            $chaosDesk->reply($reference->ulid, $reference->accessToken, $this->reply);
        } catch (ChaosDeskException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reply = '';
        unset($this->thread);
    }

    public function render(): View
    {
        return view('chaosdesk::livewire.ticket-list');
    }

    /**
     * Resolve a ticket reference, but only one belonging to the current user.
     */
    protected function reference(string $ulid): ?TicketReference
    {
        $externalId = Identity::externalId(Auth::user());

        return $externalId === null
            ? null
            : app(TicketStore::class)->find($externalId, $ulid, app(ChaosDesk::class)->site());
    }
}
