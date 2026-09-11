<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Tests\Support\PlainUser;
use ThreeOhEight\ChaosDesk\Tests\Support\User;

it('sends the site token as a header', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);

    app(ChaosDesk::class)->createTicket([
        'email' => 'ada@example.test',
        'subject' => 'Subject',
        'message' => 'Body',
    ]);

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Site-Token', 'test-site-token')
        && $request->url() === 'https://chaosdesk.test/api/v1/public/tickets');
});

it('refuses to call the api without a token', function (): void {
    config(['chaosdesk.site_token' => null]);

    expect(fn () => app(ChaosDesk::class)->config())
        ->toThrow(ChaosDeskException::class, 'No ChaosDesk site token configured');
});

it('collects server context automatically', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);
    config(['app.name' => 'Dialed', 'chaosdesk.context.app_version' => '2.4.1']);

    app(ChaosDesk::class)->createTicket([
        'email' => 'ada@example.test',
        'subject' => 'Subject',
        'message' => 'Body',
    ]);

    Http::assertSent(function ($request): bool {
        $context = $request->data()['context'];

        return $context['app']['name'] === 'Dialed'
            && $context['app']['version'] === '2.4.1'
            && $context['runtime']['php'] === PHP_VERSION
            && $context['sdk']['name'] === 'laravel-chaosdesk';
    });
});

it('lets the client report its own app version and build', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);
    config(['app.name' => 'Dialed', 'chaosdesk.context.app_version' => '2.4.1']);

    app(ChaosDesk::class)->createTicket(
        ['email' => 'ada@example.test', 'subject' => 'Subject', 'message' => 'Body'],
        clientContext: ['app' => ['version' => '3.0.0', 'build' => '512', 'name' => 'Spoofed']],
    );

    Http::assertSent(function ($request): bool {
        $app = $request->data()['context']['app'];

        return $app['version'] === '3.0.0'
            && $app['build'] === '512'
            && $app['name'] === 'Dialed'
            && isset($app['environment']);
    });
});

it('keeps the server app version when the client reports none', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);
    config(['chaosdesk.context.app_version' => '2.4.1']);

    app(ChaosDesk::class)->createTicket(
        ['email' => 'ada@example.test', 'subject' => 'Subject', 'message' => 'Body'],
        clientContext: ['app' => ['version' => '', 'build' => 381]],
    );

    Http::assertSent(function ($request): bool {
        $app = $request->data()['context']['app'];

        return $app['version'] === '2.4.1' && ! isset($app['build']);
    });
});

it('attaches the identified user', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);

    $user = new User(['id' => 7, 'name' => 'Ada', 'email' => 'ada@example.test']);

    app(ChaosDesk::class)->createTicket(
        ['email' => 'ada@example.test', 'subject' => 'Subject', 'message' => 'Body'],
        user: $user,
    );

    Http::assertSent(function ($request): bool {
        $user = $request->data()['context']['user'];

        return $user['external_id'] === 'ext-7' && $user['plan'] === 'pro' && $user['name'] === 'Ada';
    });
});

it('falls back to the auth identifier for a user without the contract', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);

    $user = new PlainUser(['id' => 9, 'name' => 'Grace', 'email' => 'grace@example.test']);

    app(ChaosDesk::class)->createTicket(
        ['email' => 'grace@example.test', 'subject' => 'Subject', 'message' => 'Body'],
        user: $user,
    );

    Http::assertSent(fn ($request): bool => $request->data()['context']['user']['external_id'] === '9');
});

it('lets the client override the page context', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);

    app(ChaosDesk::class)->createTicket(
        ['email' => 'ada@example.test', 'subject' => 'Subject', 'message' => 'Body'],
        clientContext: ['page' => ['url' => 'https://dialed.at/setups/12', 'viewport' => '1512x832']],
    );

    Http::assertSent(function ($request): bool {
        $page = $request->data()['context']['page'];

        return $page['url'] === 'https://dialed.at/setups/12' && $page['viewport'] === '1512x832';
    });
});

it('caps and normalises the console buffer', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);

    $console = collect(range(1, 60))
        ->map(fn (int $i): array => ['level' => 'nonsense', 'message' => "error {$i}"])
        ->all();

    app(ChaosDesk::class)->createTicket(
        ['email' => 'ada@example.test', 'subject' => 'Subject', 'message' => 'Body'],
        clientContext: ['console' => $console],
    );

    Http::assertSent(function ($request): bool {
        $entries = $request->data()['context']['console'];

        return count($entries) === 50
            && $entries[0]['message'] === 'error 11'
            && $entries[0]['level'] === 'error';
    });
});

it('honours disabled context groups', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);
    config(['chaosdesk.context.runtime' => false, 'chaosdesk.context.user' => false]);

    app(ChaosDesk::class)->createTicket(
        ['email' => 'ada@example.test', 'subject' => 'Subject', 'message' => 'Body'],
        user: new User(['id' => 7, 'email' => 'ada@example.test']),
    );

    Http::assertSent(function ($request): bool {
        $context = $request->data()['context'];

        return ! isset($context['runtime']) && ! isset($context['user']);
    });
});

it('turns an api error into an exception carrying the validation errors', function (): void {
    Http::fake(['*' => Http::response([
        'message' => 'The given data was invalid.',
        'errors' => ['subject' => ['A subject is required for the ticket.']],
    ], 422)]);

    try {
        app(ChaosDesk::class)->createTicket(['email' => 'ada@example.test', 'subject' => '', 'message' => 'Body']);
        $this->fail('Expected a ChaosDeskException.');
    } catch (ChaosDeskException $e) {
        expect($e->isValidationError())->toBeTrue()
            ->and($e->errors)->toHaveKey('subject');
    }
});

it('sends one idempotency key across retries', function (): void {
    config(['chaosdesk.retries' => 2]);

    Http::fake([
        '*' => Http::sequence()
            ->push(['message' => 'Server Error'], 500)
            ->push(ticketCreatedResponse(), 201),
    ]);

    app(ChaosDesk::class)->createTicket([
        'email' => 'ada@example.test',
        'subject' => 'Subject',
        'message' => 'Body',
    ]);

    $keys = idempotencyKeysSent();

    expect($keys)->toHaveCount(2)
        ->and($keys->first())->not->toBeEmpty()
        ->and($keys->unique())->toHaveCount(1);
});

it('sends a fresh idempotency key for every ticket', function (): void {
    Http::fake(['*' => Http::response(ticketCreatedResponse(), 201)]);

    $attributes = ['email' => 'ada@example.test', 'subject' => 'Subject', 'message' => 'Body'];

    app(ChaosDesk::class)->createTicket($attributes);
    app(ChaosDesk::class)->createTicket($attributes);

    $keys = idempotencyKeysSent();

    expect($keys)->toHaveCount(2)
        ->and($keys->unique())->toHaveCount(2);
});

it('sends an idempotency key when replying', function (): void {
    Http::fake(['*' => Http::response(['message' => 'ok'], 201)]);

    app(ChaosDesk::class)->reply('01JABC', 'the-access-token', 'Still broken.');

    expect(idempotencyKeysSent()->first())->not->toBeEmpty();
});

it('passes the access token when reading a ticket', function (): void {
    Http::fake(['*' => Http::response(['data' => ['subject' => 'Hi']])]);

    app(ChaosDesk::class)->ticket('01JABC', 'the-access-token');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'access_token=the-access-token'));
});
