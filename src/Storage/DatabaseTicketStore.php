<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Storage;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ThreeOhEight\ChaosDesk\Contracts\TicketStore;
use ThreeOhEight\ChaosDesk\Support\TicketReference;

/**
 * Stores ticket references in a local chaosdesk_tickets table.
 *
 * Swap it out by binding your own TicketStore implementation if you would
 * rather keep the references somewhere else.
 */
class DatabaseTicketStore implements TicketStore
{
    public function __construct(protected string $table = 'chaosdesk_tickets') {}

    public function remember(string $externalId, array $ticket, string $site = 'default'): void
    {
        DB::table($this->table)->updateOrInsert(
            ['site' => $site, 'ticket_ulid' => $ticket['ulid']],
            [
                'external_id' => $externalId,
                'ticket_id' => isset($ticket['id']) ? (int) $ticket['id'] : null,
                'access_token' => $ticket['access_token'],
                'subject' => $ticket['subject'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * @return Collection<int, TicketReference>
     */
    public function forUser(string $externalId, ?string $site = null): Collection
    {
        return $this->query($externalId, $site)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(static fn ($row): TicketReference => self::toReference($row))
            ->values();
    }

    public function find(string $externalId, string $ulid, ?string $site = null): ?TicketReference
    {
        $row = $this->query($externalId, $site)
            ->where('ticket_ulid', $ulid)
            ->first();

        return $row === null ? null : self::toReference($row);
    }

    protected function query(string $externalId, ?string $site): Builder
    {
        return DB::table($this->table)
            ->where('external_id', $externalId)
            ->when($site !== null, fn (Builder $query): Builder => $query->where('site', $site));
    }

    private static function toReference(object $row): TicketReference
    {
        return new TicketReference(
            ulid: (string) $row->ticket_ulid,
            accessToken: (string) $row->access_token,
            subject: (string) $row->subject,
            createdAt: $row->created_at === null ? null : (string) $row->created_at,
            site: (string) $row->site,
            id: $row->ticket_id === null ? null : (int) $row->ticket_id,
        );
    }
}
