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
