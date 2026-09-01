<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Contracts\TicketStore;
use ThreeOhEight\ChaosDesk\Livewire\TicketList;
use ThreeOhEight\ChaosDesk\Tests\Support\User;

/**
 * Sign in a user who already raised one ticket.
 */
function userWithTicket(string $ulid = '01JABCDEFGHIJKLMNOPQRSTUVW'): User
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    app(TicketStore::class)->remember($user->supportExternalId(), [
        'ulid' => $ulid,
        'access_token' => str_repeat('a', 64),
        'subject' => 'Cannot save my settings',
    ]);

    return $user;
}

it('shows an empty state for a user with no tickets', function (): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    Livewire::actingAs($user)
        ->test(TicketList::class)
        ->assertOk()
        ->assertSee('You have not raised any support tickets yet.');
});

it('lists the tickets belonging to the user', function (): void {
    fakeChaosDesk();

    Livewire::actingAs(userWithTicket())
        ->test(TicketList::class)
        ->assertSee('Cannot save my settings');
});

it('does not list another user tickets', function (): void {
    fakeChaosDesk();

    userWithTicket();

    $other = User::create(['name' => 'Grace', 'email' => 'grace@example.test', 'password' => 'x']);

    Livewire::actingAs($other)
        ->test(TicketList::class)
        ->assertDontSee('Cannot save my settings');
});

it('opens a thread using the stored access token', function (): void {
    fakeChaosDesk();

    Livewire::actingAs(userWithTicket())
        ->test(TicketList::class)
        ->call('open', '01JABCDEFGHIJKLMNOPQRSTUVW')
        ->assertSee('It reverts every time.');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'access_token='.str_repeat('a', 64)));
});

it('refuses to open a ticket the user does not own', function (): void {
    fakeChaosDesk();

    userWithTicket();

    $other = User::create(['name' => 'Grace', 'email' => 'grace@example.test', 'password' => 'x']);

    Livewire::actingAs($other)
        ->test(TicketList::class)
        ->call('open', '01JABCDEFGHIJKLMNOPQRSTUVW')
        ->assertDontSee('It reverts every time.');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '01JABCDEFGHIJKLMNOPQRSTUVW'));
});

it('sends a reply on the open thread', function (): void {
    fakeChaosDesk();

    Livewire::actingAs(userWithTicket())
        ->test(TicketList::class)
        ->call('open', '01JABCDEFGHIJKLMNOPQRSTUVW')
        ->set('reply', 'Still broken, here is more detail.')
        ->call('sendReply')
        ->assertHasNoErrors()
        ->assertSet('reply', '');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/messages')
        && $request->data()['body'] === 'Still broken, here is more detail.');
});

it('requires a reply body', function (): void {
    fakeChaosDesk();

    Livewire::actingAs(userWithTicket())
        ->test(TicketList::class)
        ->call('open', '01JABCDEFGHIJKLMNOPQRSTUVW')
        ->call('sendReply')
        ->assertHasErrors('reply');
});

it('surfaces a failed reply', function (): void {
    fakeChaosDesk(['*/messages*' => Http::response(['message' => 'Ticket is locked.'], 403)]);

    Livewire::actingAs(userWithTicket())
        ->test(TicketList::class)
        ->call('open', '01JABCDEFGHIJKLMNOPQRSTUVW')
        ->set('reply', 'Hello?')
        ->call('sendReply')
        ->assertSet('error', 'Ticket is locked.');
});

it('shows nothing for a guest', function (): void {
    fakeChaosDesk();

    Livewire::test(TicketList::class)
        ->assertOk()
        ->assertSee('You have not raised any support tickets yet.');
});
