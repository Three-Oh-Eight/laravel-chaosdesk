<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use ThreeOhEight\ChaosDesk\Contracts\TicketStore;
use ThreeOhEight\ChaosDesk\Support\TicketReference;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storedTicket(array $overrides = []): array
{
    return $overrides + [
        'id' => 42,
        'ulid' => '01JABCDEFGHIJKLMNOPQRSTUVW',
        'access_token' => str_repeat('a', 64),
        'subject' => 'Cannot save my settings',
    ];
}

it('writes the site and the numeric ticket id', function (): void {
    app(TicketStore::class)->remember('ext-1', storedTicket(), 'gurus');

    $row = DB::table('chaosdesk_tickets')->sole();

    expect($row->site)->toBe('gurus')
        ->and((int) $row->ticket_id)->toBe(42)
        ->and($row->external_id)->toBe('ext-1')
        ->and($row->ticket_ulid)->toBe('01JABCDEFGHIJKLMNOPQRSTUVW');
});

it('defaults to the default site and tolerates a missing id', function (): void {
    app(TicketStore::class)->remember('ext-1', [
        'ulid' => '01JABCDEFGHIJKLMNOPQRSTUVW',
        'access_token' => str_repeat('a', 64),
        'subject' => 'Cannot save my settings',
    ]);

    $row = DB::table('chaosdesk_tickets')->sole();

    expect($row->site)->toBe('default')
        ->and($row->ticket_id)->toBeNull();
});

it('keeps one row per site and ulid', function (): void {
    $store = app(TicketStore::class);

    $store->remember('ext-1', storedTicket(), 'customers');
    $store->remember('ext-1', storedTicket(['subject' => 'Renamed']), 'customers');
    $store->remember('ext-1', storedTicket(['id' => 7]), 'gurus');

    expect(DB::table('chaosdesk_tickets')->count())->toBe(2)
        ->and(DB::table('chaosdesk_tickets')->where('site', 'customers')->value('subject'))->toBe('Renamed')
        ->and((int) DB::table('chaosdesk_tickets')->where('site', 'gurus')->value('ticket_id'))->toBe(7);
});

it('lists a user tickets per site or across all sites', function (): void {
    $store = app(TicketStore::class);

    $store->remember('ext-1', storedTicket(['ulid' => '01JAAAAAAAAAAAAAAAAAAAAAAA']), 'customers');
    $store->remember('ext-1', storedTicket(['ulid' => '01JBBBBBBBBBBBBBBBBBBBBBBB']), 'gurus');
    $store->remember('ext-2', storedTicket(['ulid' => '01JCCCCCCCCCCCCCCCCCCCCCCC']), 'customers');

    expect($store->forUser('ext-1')->map(fn (TicketReference $ticket): string => $ticket->site)->sort()->values()->all())
        ->toBe(['customers', 'gurus'])
        ->and($store->forUser('ext-1', 'customers')->pluck('ulid')->all())->toBe(['01JAAAAAAAAAAAAAAAAAAAAAAA'])
        ->and($store->forUser('ext-1', 'gurus')->pluck('ulid')->all())->toBe(['01JBBBBBBBBBBBBBBBBBBBBBBB'])
        ->and($store->forUser('ext-1', 'nowhere'))->toBeEmpty()
        ->and($store->forUser('ext-2'))->toHaveCount(1);
});

it('scopes a lookup by site', function (): void {
    $store = app(TicketStore::class);

    $store->remember('ext-1', storedTicket(['access_token' => str_repeat('c', 64)]), 'customers');
    $store->remember('ext-1', storedTicket(['access_token' => str_repeat('g', 64)]), 'gurus');

    $ulid = '01JABCDEFGHIJKLMNOPQRSTUVW';

    expect($store->find('ext-1', $ulid, 'customers')?->accessToken)->toBe(str_repeat('c', 64))
        ->and($store->find('ext-1', $ulid, 'gurus')?->accessToken)->toBe(str_repeat('g', 64))
        ->and($store->find('ext-1', $ulid, 'nowhere'))->toBeNull()
        ->and($store->find('ext-2', $ulid, 'customers'))->toBeNull()
        ->and($store->find('ext-1', $ulid))->toBeInstanceOf(TicketReference::class);
});

it('exposes the site and id on the reference', function (): void {
    app(TicketStore::class)->remember('ext-1', storedTicket(), 'gurus');

    $reference = app(TicketStore::class)->find('ext-1', '01JABCDEFGHIJKLMNOPQRSTUVW', 'gurus');

    expect($reference)->toBeInstanceOf(TicketReference::class)
        ->and($reference->site)->toBe('gurus')
        ->and($reference->id)->toBe(42)
        ->and($reference->toArray())->toMatchArray([
            'ulid' => '01JABCDEFGHIJKLMNOPQRSTUVW',
            'subject' => 'Cannot save my settings',
            'site' => 'gurus',
            'id' => 42,
        ]);
});

it('builds a reference with positional arguments as before', function (): void {
    $reference = new TicketReference('01JABC', 'token', 'Subject', '2026-09-01 09:00:00');

    expect($reference->site)->toBe('default')
        ->and($reference->id)->toBeNull()
        ->and($reference->toArray())->toHaveKeys(['ulid', 'subject', 'created_at', 'site', 'id']);
});
