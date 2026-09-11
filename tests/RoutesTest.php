<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use ThreeOhEight\ChaosDesk\Facades\ChaosDesk;
use ThreeOhEight\ChaosDesk\Tests\Support\User;

/**
 * Register the SDK routes behind the host application's own auth, exactly as
 * the documentation tells an integrator to.
 */
beforeEach(function (): void {
    Route::middleware(['web', 'auth'])->group(function (): void {
        ChaosDesk::routes();
    });
});

it('rejects an unauthenticated submission', function (): void {
    fakeChaosDesk();

    $this->postJson('/chaosdesk/tickets', [
        'subject' => 'Subject',
        'message' => 'Body',
    ])->assertUnauthorized();
});

it('forwards an authenticated submission to chaosdesk', function (): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    $this->actingAs($user)
        ->postJson('/chaosdesk/tickets', [
            'subject' => 'Fork sag not saving',
            'message' => 'It reverts every time.',
            'context' => [
                'source' => 'ios',
                'device' => ['platform' => 'ios', 'os_version' => '26.1', 'model' => 'iPhone17,2'],
                'app' => ['version' => '2.4.1', 'build' => '381'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('ticket.ulid', '01JABCDEFGHIJKLMNOPQRSTUVW');

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $data['email'] === 'ada@example.test'
            && $data['context']['source'] === 'ios'
            && $data['context']['device']['model'] === 'iPhone17,2'
            && $data['context']['user']['external_id'] === 'ext-1';
    });
});

it('records the ticket so the user can find it again', function (): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    $this->actingAs($user)
        ->postJson('/chaosdesk/tickets', ['subject' => 'Subject', 'message' => 'Body'])
        ->assertCreated();

    expect(DB::table('chaosdesk_tickets')->where('external_id', 'ext-'.$user->id)->count())->toBe(1);

    $this->actingAs($user)
        ->getJson('/chaosdesk/tickets')
        ->assertOk()
        ->assertJsonPath('data.0.ulid', '01JABCDEFGHIJKLMNOPQRSTUVW');
});

it('validates the submission', function (): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    $this->actingAs($user)
        ->postJson('/chaosdesk/tickets', ['subject' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'message']);
});

it('forwards well-formed tags and extra context', function (): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    $this->actingAs($user)
        ->postJson('/chaosdesk/tickets', [
            'subject' => 'Subject',
            'message' => 'Body',
            'tags' => ['in-session', 'billing'],
            'context' => ['extra' => ['chat_id' => 12, 'channel' => 'video', 'note.v2' => 'ok']],
        ])
        ->assertCreated();

    Http::assertSent(fn ($request): bool => $request->data()['tags'] === ['in-session', 'billing']
        && $request->data()['context']['extra']['chat_id'] === 12);
});

it('rejects malformed tags', function (array $tags): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    $response = $this->actingAs($user)
        ->postJson('/chaosdesk/tickets', ['subject' => 'Subject', 'message' => 'Body', 'tags' => $tags])
        ->assertUnprocessable();

    $keys = array_keys((array) $response->json('errors'));

    expect(array_filter($keys, fn (string $key): bool => str_starts_with($key, 'tags')))->not->toBeEmpty();

    Http::assertNothingSent();
})->with([
    'uppercase' => [['Billing']],
    'spaces' => [['in session']],
    'too long' => [[str_repeat('a', 33)]],
    'duplicate' => [['billing', 'billing']],
    'too many' => [array_map(fn (int $i): string => "tag-{$i}", range(1, 11))],
]);

it('rejects malformed extra context', function (array $extra): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    $this->actingAs($user)
        ->postJson('/chaosdesk/tickets', ['subject' => 'Subject', 'message' => 'Body', 'context' => ['extra' => $extra]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['context.extra']);

    Http::assertNothingSent();
})->with([
    'bad key' => [['chat id' => 12]],
    'long key' => [[str_repeat('k', 65) => 12]],
    'nested value' => [['chat' => ['id' => 12]]],
    'long value' => [['note' => str_repeat('x', 256)]],
    'too many keys' => [array_fill_keys(array_map(fn (int $i): string => "key_{$i}", range(1, 21)), 1)],
]);

it('rejects an oversized or non-string app version and build', function (array $app, array $fields): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    $this->actingAs($user)
        ->postJson('/chaosdesk/tickets', ['subject' => 'Subject', 'message' => 'Body', 'context' => ['app' => $app]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($fields);

    Http::assertNothingSent();
})->with([
    'long version' => [['version' => str_repeat('9', 65)], ['context.app.version']],
    'long build' => [['build' => str_repeat('9', 65)], ['context.app.build']],
    'array version' => [['version' => ['2', '4']], ['context.app.version']],
    'both' => [['version' => str_repeat('9', 65), 'build' => str_repeat('9', 65)], ['context.app.version', 'context.app.build']],
]);

it('reports an upstream failure as a bad gateway', function (): void {
    fakeChaosDesk(['*/public/tickets*' => Http::response(['message' => 'Site is not verified.'], 401)]);

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    $this->actingAs($user)
        ->postJson('/chaosdesk/tickets', ['subject' => 'Subject', 'message' => 'Body'])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Site is not verified.');
});

it('will not read a ticket belonging to somebody else', function (): void {
    fakeChaosDesk();

    $ada = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);
    $grace = User::create(['name' => 'Grace', 'email' => 'grace@example.test', 'password' => 'x']);

    $this->actingAs($ada)
        ->postJson('/chaosdesk/tickets', ['subject' => 'Subject', 'message' => 'Body'])
        ->assertCreated();

    $this->actingAs($grace)
        ->getJson('/chaosdesk/tickets/01JABCDEFGHIJKLMNOPQRSTUVW')
        ->assertNotFound();
});

it('replies to an owned ticket', function (): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    $this->actingAs($user)
        ->postJson('/chaosdesk/tickets', ['subject' => 'Subject', 'message' => 'Body'])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson('/chaosdesk/tickets/01JABCDEFGHIJKLMNOPQRSTUVW/messages', ['body' => 'Any news?'])
        ->assertCreated();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/messages')
        && $request->data()['body'] === 'Any news?');
});
