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
 *
 * References are kept per site: the same user may hold tickets on several
 * ChaosDesk sites, and a ulid is only unique within one of them.
 */
interface TicketStore
{
    /**
     * Record a ticket raised by the given user on the given site.
     *
     * @param  array{ulid: string, access_token: string, subject: string, id?: int|null}  $ticket
     */
    public function remember(string $externalId, array $ticket, string $site = 'default'): void;

    /**
     * All tickets raised by the given user, newest first.
     *
     * Pass a site name to limit the list to that site; null spans all sites.
     *
     * @return Collection<int, TicketReference>
     */
    public function forUser(string $externalId, ?string $site = null): Collection;

    /**
     * One ticket, but only if it belongs to the given user.
     *
     * Pass a site name to scope the lookup; null searches every site.
     */
    public function find(string $externalId, string $ulid, ?string $site = null): ?TicketReference;
}
