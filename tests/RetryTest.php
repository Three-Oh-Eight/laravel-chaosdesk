<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;

const RETRY_TICKET = '01JABCDEFGHIJKLMNOPQRSTUVW';

beforeEach(function (): void {
    Sleep::fake();

    config([
        'chaosdesk.retries' => 2,
        'chaosdesk.sites.customers' => ['token' => 'customers-token', 'agent_token' => 'customers-agent-token', 'site_id' => 2],
    ]);
});

/**
 * Create a ticket, the write every retry test below goes through.
 *
 * @return array<string, mixed>
 */
function createRetryTicket(?string $idempotencyKey = null): array
{
    return app(ChaosDesk::class)->createTicket(
        ['email' => 'ada@example.test', 'subject' => 'Subject', 'message' => 'Body'],
        idempotencyKey: $idempotencyKey,
    );
}

it('does not retry a client error', function (int $status): void {
    Http::fake(['*' => Http::sequence()
        ->push(['message' => 'No.'], $status)
        ->push(ticketCreatedResponse(), 201)]);

    expect(fn () => createRetryTicket())
        ->toThrow(function (ChaosDeskException $e) use ($status): void {
            expect($e->status)->toBe($status);
        });

    Http::assertSentCount(1);
})->with([
    'unauthorised' => 401,
    'not found' => 404,
    'validation' => 422,
]);

it('does not retry a client error on a read', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['message' => 'Ticket not found.'], 404)
        ->push(['data' => ticketPayload()])]);

    expect(fn () => app(ChaosDesk::class)->ticket(RETRY_TICKET, 'token'))
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isNotFound())->toBeTrue();
        });

    Http::assertSentCount(1);
});

it('retries a keyed write once on a transient answer with the same key', function (int $status): void {
    Http::fake(['*' => Http::sequence()
        ->push(['message' => 'Try again.'], $status)
        ->push(ticketCreatedResponse(), 201)]);

    $result = createRetryTicket();

    $keys = idempotencyKeysSent();

    expect($result['ticket']['ulid'])->toBe(RETRY_TICKET)
        ->and($keys)->toHaveCount(2)
        ->and($keys->first())->not->toBeEmpty()
        ->and($keys->unique())->toHaveCount(1);
})->with([
    'server error' => 500,
    'unavailable' => 503,
    'too many requests' => 429,
]);

it('retries a keyed write once after a connection failure', function (): void {
    Http::fake(['*' => Http::sequence()
        ->pushFailedConnection('Connection refused')
        ->push(ticketCreatedResponse(), 201)]);

    createRetryTicket();

    $keys = idempotencyKeysSent();

    expect($keys)->toHaveCount(2)
        ->and($keys->unique())->toHaveCount(1);
});

it('retries a read on a transient answer', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['message' => 'Server Error'], 500)
        ->push(['data' => ticketPayload()])]);

    $ticket = app(ChaosDesk::class)->ticket(RETRY_TICKET, 'token');

    expect($ticket['data']['ulid'])->toBe(RETRY_TICKET);

    Http::assertSentCount(2);
});

it('retries a read after a connection failure', function (): void {
    Http::fake(['*' => Http::sequence()
        ->pushFailedConnection('Connection refused')
        ->push(['site' => ['name' => 'Acme']])]);

    expect(app(ChaosDesk::class)->config()['site']['name'])->toBe('Acme');

    Http::assertSentCount(2);
});

it('gives up after the configured attempts and reports the outage', function (): void {
    Http::fake(['*' => Http::failedConnection('Connection refused')]);

    expect(fn () => createRetryTicket())
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isUnavailable())->toBeTrue();
        });

    Http::assertSentCount(2);
});

it('never retries a write without an idempotency key', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['message' => 'Server Error'], 500)
        ->push(['message' => 'Author anonymised.'])]);

    expect(fn () => app(ChaosDesk::class)->agent('customers')->anonymiseAuthor('user:7', 2))
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isUnavailable())->toBeTrue();
        });

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && ! $request->hasHeader('Idempotency-Key'));
});

it('never retries a write without an idempotency key after a connection failure', function (): void {
    Http::fake(['*' => Http::sequence()
        ->pushFailedConnection('Connection refused')
        ->push(['data' => agentTicketPayload()])]);

    expect(fn () => app(ChaosDesk::class)->agent('customers')->update(RETRY_TICKET, ['status' => 'pending']))
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isUnavailable())->toBeTrue();
        });

    Http::assertSentCount(1);
});

it('forwards the caller idempotency key unchanged', function (Closure $write): void {
    fakeChaosDeskAgent();

    $write(app(ChaosDesk::class));

    expect(idempotencyKeysSent()->all())->toBe(['caller-key-123']);
})->with([
    // The test parameter is typed Closure, so Pest hands each closure over
    // as is instead of resolving it as a bound dataset value.
    'create ticket' => [fn (ChaosDesk $desk): array => $desk->createTicket(
        ['email' => 'ada@example.test', 'subject' => 'Subject', 'message' => 'Body'],
        idempotencyKey: 'caller-key-123',
    )],
    'requester reply' => [fn (ChaosDesk $desk): array => $desk->reply(RETRY_TICKET, 'token', 'Any news?', idempotencyKey: 'caller-key-123')],
    'attach' => [fn (ChaosDesk $desk): array => $desk->attach(
        RETRY_TICKET,
        'token',
        UploadedFile::fake()->createWithContent('log.txt', 'boom'),
        idempotencyKey: 'caller-key-123',
    )],
    'attach contents' => [fn (ChaosDesk $desk): array => $desk->attachContents(RETRY_TICKET, 'token', 'boom', 'log.txt', idempotencyKey: 'caller-key-123')],
    'agent reply' => [fn (ChaosDesk $desk): array => $desk->agent('customers')->reply(RETRY_TICKET, 'On it.', idempotencyKey: 'caller-key-123')],
    'agent note' => [fn (ChaosDesk $desk): array => $desk->agent('customers')->note(RETRY_TICKET, 'Checked.', idempotencyKey: 'caller-key-123')],
]);

it('keeps the caller idempotency key across a retry', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['message' => 'Server Error'], 502)
        ->push(ticketCreatedResponse(), 201)]);

    createRetryTicket('outreach-reply:42');

    expect(idempotencyKeysSent()->all())->toBe(['outreach-reply:42', 'outreach-reply:42']);
});

it('sends a fresh idempotency key when the caller passes an empty one', function (): void {
    fakeChaosDesk();

    createRetryTicket('');

    expect(idempotencyKeysSent()->first())->not->toBeEmpty();
});

it('sends an idempotency key with every attachment', function (): void {
    fakeChaosDesk();

    $desk = app(ChaosDesk::class);
    $desk->attachContents(RETRY_TICKET, 'token', 'one', 'one.txt');
    $desk->attach(RETRY_TICKET, 'token', UploadedFile::fake()->createWithContent('two.txt', 'two'));

    $keys = idempotencyKeysSent();

    expect($keys)->toHaveCount(2)
        ->and($keys->filter()->unique())->toHaveCount(2);
});

it('retries an attachment with the same key', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['message' => 'Server Error'], 500)
        ->push(['message' => 'ok'], 201)]);

    app(ChaosDesk::class)->attachContents(RETRY_TICKET, 'token', 'boom', 'log.txt');

    $keys = idempotencyKeysSent();

    expect($keys)->toHaveCount(2)
        ->and($keys->first())->not->toBeEmpty()
        ->and($keys->unique())->toHaveCount(1);
});
