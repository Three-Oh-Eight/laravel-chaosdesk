<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Contracts;

use Illuminate\Support\Collection;
use ThreeOhEight\ChaosDesk\Support\TicketReference;

/**
 * Remembers which ChaosDesk tickets belong to which of your users.
 *
 * ChaosDesk hands back a per-ticket access token when a ticket is created.
 * That token is the credential for reading and replying to the thread, so your
 * application has to keep it somewhere to show the user their own tickets.
 */
interface TicketStore
{
    /**
     * Record a ticket raised by the given user.
     *
     * @param  array{ulid: string, access_token: string, subject: string}  $ticket
     */
    public function remember(string $externalId, array $ticket): void;

    /**
     * All tickets raised by the given user, newest first.
     *
     * @return Collection<int, TicketReference>
     */
    public function forUser(string $externalId): Collection;

    /**
     * One ticket, but only if it belongs to the given user.
     */
    public function find(string $externalId, string $ulid): ?TicketReference;
}
