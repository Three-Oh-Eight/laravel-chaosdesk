<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Livewire\SupportForm;
use ThreeOhEight\ChaosDesk\Tests\Support\User;

// Each test registers its own fakes: Http::fake() merges rather than replaces,
// so a shared beforeEach would always win over a per-test override.

it('renders', function (): void {
    fakeChaosDesk();

    Livewire::test(SupportForm::class)->assertOk();
});

it('requires a subject and a message', function (): void {
    fakeChaosDesk();

    Livewire::test(SupportForm::class)
        ->set('email', 'ada@example.test')
        ->call('submit')
        ->assertHasErrors(['subject' => 'required', 'message' => 'required']);
});

it('submits a ticket and confirms', function (): void {
    fakeChaosDesk();

    Livewire::test(SupportForm::class)
        ->set('email', 'ada@example.test')
        ->set('subject', 'Cannot save my settings')
        ->set('message', 'It reverts every time.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertDispatched('chaosdesk-ticket-created');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/public/tickets')
        && $request->data()['subject'] === 'Cannot save my settings');
});

it('prefills from the signed-in user', function (): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    Livewire::actingAs($user)
        ->test(SupportForm::class)
        ->assertSet('email', 'ada@example.test')
        ->assertSet('name', 'Ada');
});

it('remembers the ticket against the signed-in user', function (): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    Livewire::actingAs($user)
        ->test(SupportForm::class)
        ->set('subject', 'Subject')
        ->set('message', 'Body')
        ->call('submit')
        ->assertHasNoErrors();

    $row = DB::table('chaosdesk_tickets')->sole();

    expect($row->external_id)->toBe('ext-'.$user->id)
        ->and($row->ticket_ulid)->toBe('01JABCDEFGHIJKLMNOPQRSTUVW')
        ->and($row->access_token)->toBe(str_repeat('a', 64));
});

it('does not record a ticket for a guest', function (): void {
    fakeChaosDesk();

    Livewire::test(SupportForm::class)
        ->set('email', 'ada@example.test')
        ->set('subject', 'Subject')
        ->set('message', 'Body')
        ->call('submit')
        ->assertHasNoErrors();

    expect(DB::table('chaosdesk_tickets')->count())->toBe(0);
});

it('sends the browser context along with the ticket', function (): void {
    fakeChaosDesk();

    Livewire::test(SupportForm::class)
        ->set('email', 'ada@example.test')
        ->set('subject', 'Subject')
        ->set('message', 'Body')
        ->set('clientContext', [
            'page' => ['url' => 'https://dialed.at/setups/12'],
            'console' => [['level' => 'error', 'message' => 'Uncaught TypeError']],
        ])
        ->call('submit')
        ->assertHasNoErrors();

    Http::assertSent(function ($request): bool {
        if (! str_ends_with($request->url(), '/public/tickets')) {
            return false;
        }

        $context = $request->data()['context'];

        return $context['page']['url'] === 'https://dialed.at/setups/12'
            && $context['console'][0]['message'] === 'Uncaught TypeError';
    });
});

it('uploads a captured screenshot after creating the ticket', function (): void {
    fakeChaosDesk();

    $png = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

    Livewire::test(SupportForm::class)
        ->set('email', 'ada@example.test')
        ->set('subject', 'Subject')
        ->set('message', 'Body')
        ->set('screenshot', $png)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('screenshot', null);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/attachments')
        && str_contains($request->url(), 'access_token='.str_repeat('a', 64)));
});

it('ignores a screenshot that is not a png data url', function (): void {
    fakeChaosDesk();

    Livewire::test(SupportForm::class)
        ->set('email', 'ada@example.test')
        ->set('subject', 'Subject')
        ->set('message', 'Body')
        ->set('screenshot', 'https://evil.test/not-a-data-url')
        ->call('submit')
        ->assertHasNoErrors();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/attachments'));
});

it('keeps the ticket when the screenshot upload fails', function (): void {
    fakeChaosDesk(['*/attachments*' => Http::response(['message' => 'Too large'], 422)]);

    Livewire::test(SupportForm::class)
        ->set('email', 'ada@example.test')
        ->set('subject', 'Subject')
        ->set('message', 'Body')
        ->set('screenshot', 'data:image/png;base64,'.base64_encode('bytes'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);
});

it('surfaces an api failure without losing what was typed', function (): void {
    fakeChaosDesk(['*/public/tickets*' => Http::response(['message' => 'Site is not verified.'], 401)]);

    Livewire::test(SupportForm::class)
        ->set('email', 'ada@example.test')
        ->set('subject', 'Subject')
        ->set('message', 'Body')
        ->call('submit')
        ->assertSet('submitted', false)
        ->assertSet('error', 'Site is not verified.')
        ->assertSet('subject', 'Subject');
});

it('requires an email when nobody is signed in', function (): void {
    fakeChaosDesk();

    Livewire::test(SupportForm::class)
        ->set('subject', 'Subject')
        ->set('message', 'Body')
        ->call('submit')
        ->assertHasErrors('email');
});
