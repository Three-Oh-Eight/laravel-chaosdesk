<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Tests\Support\User;

/**
 * Captures the exact payload the SDK sends, so the ChaosDesk side can assert
 * against the same fixture rather than a hand-written approximation.
 */
it('emits a payload matching the documented contract', function (): void {
    fakeChaosDesk();

    config(['app.name' => 'Dialed', 'chaosdesk.context.app_version' => '2.4.1']);

    app(ChaosDesk::class)->createTicket(
        attributes: [
            'email' => 'ada@dialed.at',
            'name' => 'Ada',
            'subject' => 'Fork sag not saving',
            'message' => 'It reverts every time I hit save.',
        ],
        clientContext: [
            'source' => 'web',
            'page' => ['url' => 'https://dialed.at/setups/12', 'viewport' => '1512x832'],
            'device' => ['platform' => 'web', 'locale' => 'nl-NL', 'timezone' => 'Europe/Amsterdam'],
            'console' => [['level' => 'error', 'message' => 'Uncaught TypeError', 'at' => '2026-09-01T09:00:00Z']],
            'extra' => ['setup_id' => 12],
        ],
        user: new User(['id' => 4711, 'name' => 'Ada', 'email' => 'ada@dialed.at']),
    );

    $payload = null;
    Http::assertSent(function ($request) use (&$payload): bool {
        if (str_ends_with($request->url(), '/public/tickets')) {
            $payload = $request->data();
        }

        return true;
    });

    expect($payload)->not->toBeNull();

    $path = getenv('CHAOSDESK_CONTRACT_FIXTURE');

    if (is_string($path) && $path !== '') {
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // The shape ChaosDesk's StoreTicketRequest validates.
    expect($payload)->toHaveKeys(['email', 'name', 'subject', 'message', 'context'])
        ->and($payload['context'])->toHaveKeys(['source', 'sdk', 'app', 'runtime', 'page', 'device', 'user', 'console', 'extra'])
        ->and($payload['context']['source'])->toBe('web')
        ->and($payload['context']['user']['external_id'])->toBe('ext-4711')
        ->and($payload['context']['user']['plan'])->toBe('pro');
});
