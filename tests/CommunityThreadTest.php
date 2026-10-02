<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Community\CommunityUrls;
use ThreeOhEight\ChaosDesk\Livewire\CommunityCharter;
use ThreeOhEight\ChaosDesk\Livewire\CommunityThread;

afterEach(function (): void {
    CommunityUrls::resolveUsing(null);
});

const COMMUNITY_THREAD = '01JTHREADPROPOSAL000000000';

it('shows the thread with an official badge on the team reply only', function (): void {
    fakeCommunityMember();
    communityUser();

    $component = Livewire::test(CommunityThread::class, ['board' => 'gurus', 'thread' => COMMUNITY_THREAD])
        ->assertOk()
        ->assertSee('Export invoices to CSV')
        ->assertSee('I would use this every week.')
        ->assertSee('This is planned for the next release.')
        ->assertSeeHtml('<span class="rounded-full bg-blue-600 px-2 py-0.5 text-xs font-medium text-white dark:bg-blue-500">Official</span>')
        ->assertSeeHtml('<form wire:submit="sendReply" class="space-y-2">');

    expect(substr_count($component->html(), '>Official</span>'))->toBe(1);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads/'.COMMUNITY_THREAD);
});

it('posts a reply to the thread with an idempotency key', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityThread::class, ['board' => 'gurus', 'thread' => COMMUNITY_THREAD])
        ->set('reply', 'Same here.')
        ->call('sendReply')
        ->assertHasNoErrors()
        ->assertSet('error', null)
        ->assertSet('reply', '');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads/'.COMMUNITY_THREAD.'/posts'
        && $request['body'] === 'Same here.'
        && ($request->header('Idempotency-Key')[0] ?? '') !== '');
});

it('requires a reply body before posting', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityThread::class, ['board' => 'gurus', 'thread' => COMMUNITY_THREAD])
        ->call('sendReply')
        ->assertHasErrors(['reply' => 'required']);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('hides the reply form on a locked thread', function (): void {
    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/threads/*' => Http::response(['data' => communityThreadPayload([
            'is_locked' => true,
            'posts' => [communityPostPayload()],
        ])]),
    ]);
    communityUser();

    Livewire::test(CommunityThread::class, ['board' => 'gurus', 'thread' => COMMUNITY_THREAD])
        ->assertSee('Export invoices to CSV')
        ->assertSee('This thread is locked. You can read it, but no longer reply.')
        ->assertDontSeeHtml('wire:submit="sendReply"');
});

it('shows a friendly message when the thread was locked meanwhile', function (): void {
    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/threads/*/posts' => Http::response([
            'message' => 'Raw API refusal text.',
            'error' => 'thread_locked',
        ], 409),
    ]);
    communityUser();

    Livewire::test(CommunityThread::class, ['board' => 'gurus', 'thread' => COMMUNITY_THREAD])
        ->set('reply', 'Same here.')
        ->call('sendReply')
        ->assertSee('This thread is locked and takes no new replies.')
        ->assertDontSee('Raw API refusal text.')
        ->assertSet('reply', 'Same here.');
});

it('toggles the upvote from the thread', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityThread::class, ['board' => 'gurus', 'thread' => COMMUNITY_THREAD])
        ->call('toggleVote')
        ->assertSet('error', null);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads/'.COMMUNITY_THREAD.'/vote');
});

it('renders the charter until the member accepted it', function (): void {
    fakeChaosDeskCommunity();
    communityUser();

    Livewire::test(CommunityThread::class, ['board' => 'gurus', 'thread' => COMMUNITY_THREAD])
        ->assertSeeLivewire(CommunityCharter::class)
        ->assertDontSee('Export invoices to CSV');
});

it('keeps the board slug in every request', function (): void {
    fakeCommunityMember(['slug' => 'clients']);
    communityUser();

    Livewire::test(CommunityThread::class, ['board' => 'clients', 'thread' => COMMUNITY_THREAD])
        ->call('toggleVote')
        ->set('reply', 'Same here.')
        ->call('sendReply');

    $requests = communityRequests();

    expect($requests)->not->toBeEmpty()
        ->and($requests->reject(fn (Request $request): bool => preg_match(
            '#^'.preg_quote(COMMUNITY_URL, '#').'/boards/clients(/|\?|$)#',
            $request->url(),
        ) === 1))->toBeEmpty();
});
