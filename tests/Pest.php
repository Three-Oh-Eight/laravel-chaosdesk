<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ThreeOhEight\ChaosDesk\Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in(__DIR__);

/**
 * Fake the ChaosDesk API.
 *
 * Overrides are merged over the defaults before registering, because a second
 * Http::fake() call adds to the existing stubs rather than replacing them.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeChaosDesk(array $overrides = []): void
{
    Http::fake($overrides + [
        '*/public/config*' => Http::response([
            'site' => ['name' => 'Acme', 'description' => null],
            'categories' => [],
            'priorities' => [],
            'custom_fields' => [],
        ]),
        '*/attachments*' => Http::response(['message' => 'ok'], 201),
        '*/messages*' => Http::response(['message' => 'ok'], 201),
        '*/public/tickets/*' => Http::response(['data' => ticketPayload()]),
        '*/public/tickets*' => Http::response(ticketCreatedResponse(), 201),
    ]);
}

/**
 * A ticket as the API returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ticketPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 1,
        'ulid' => '01JABCDEFGHIJKLMNOPQRSTUVW',
        'subject' => 'Cannot save my settings',
        'status' => 'open',
        'messages' => [
            ['id' => 1, 'body' => 'It reverts every time.', 'author_name' => 'Ada', 'is_agent' => false, 'created_at' => '2026-09-01T09:00:00+00:00'],
        ],
    ], $overrides);
}

/**
 * A successful ticket-creation response from the ChaosDesk API.
 */
function ticketCreatedResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'message' => 'Ticket created successfully.',
        'ticket' => [
            'id' => 1,
            'ulid' => '01JABCDEFGHIJKLMNOPQRSTUVW',
            'subject' => 'Cannot save my settings',
            'status' => 'open',
        ],
        'access_token' => str_repeat('a', 64),
    ], $overrides);
}
