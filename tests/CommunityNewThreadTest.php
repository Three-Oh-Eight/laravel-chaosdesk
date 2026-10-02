<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Community\CommunityUrls;
use ThreeOhEight\ChaosDesk\Livewire\CommunityCharter;
use ThreeOhEight\ChaosDesk\Livewire\CommunityNewThread;

afterEach(function (): void {
    CommunityUrls::resolveUsing(null);
});

/**
 * The thread-creation requests the fake recorded, in order.
 *
 * @return list<Request>
 */
function threadCreationRequests(): array
{
    return communityRequests()
        ->filter(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === COMMUNITY_URL.'/boards/gurus/threads')
        ->values()
        ->all();
}

it('posts a new thread with an idempotency key', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityNewThread::class, ['board' => 'gurus'])
        ->assertOk()
        ->set('kind', 'proposal')
        ->set('title', 'Dark mode')
        ->set('body', 'Please add a dark mode.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('error', null)
        ->assertSet('createdThread', '01JTHREADNEW00000000000000')
        ->assertSet('title', '')
        ->assertDispatched('chaosdesk-community-thread-created')
        ->assertSee('Your thread is posted.');

    $requests = threadCreationRequests();

    expect($requests)->toHaveCount(1)
        ->and($requests[0]->data())->toBe(['kind' => 'proposal', 'title' => 'Dark mode', 'body' => 'Please add a dark mode.'])
        ->and($requests[0]->header('Idempotency-Key')[0] ?? '')->not->toBe('');
});

it('goes to the configured thread url after posting', function (): void {
    fakeCommunityMember();
    communityUser();

    CommunityUrls::resolveUsing(fn (string $page, string $board, ?string $thread): ?string => $page === CommunityUrls::THREAD
        ? "https://host.test/community/{$board}/{$thread}"
        : null);

    Livewire::test(CommunityNewThread::class, ['board' => 'gurus'])
        ->set('kind', 'discussion')
        ->set('title', 'Dark mode')
        ->set('body', 'Please add a dark mode.')
        ->call('submit')
        ->assertRedirect('https://host.test/community/gurus/01JTHREADNEW00000000000000');
});

it('resends the same idempotency key when the same submit is retried', function (): void {
    $attempts = 0;

    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/threads*' => function () use (&$attempts) {
            $attempts++;

            return $attempts === 1
                ? Http::response(['message' => 'Service Unavailable'], 503)
                : Http::response([
                    'message' => 'Thread created successfully.',
                    'thread' => communityThreadPayload(['ulid' => '01JTHREADNEW00000000000000', 'title' => 'Dark mode'], withVote: false),
                ], 201);
        },
    ]);
    communityUser();

    $component = Livewire::test(CommunityNewThread::class, ['board' => 'gurus'])
        ->set('kind', 'proposal')
        ->set('title', 'Dark mode')
        ->set('body', 'Please add a dark mode.')
        ->call('submit')
        ->assertSee('The community is unavailable right now. Please try again shortly.')
        ->assertSet('createdThread', null)
        ->call('submit')
        ->assertSet('error', null)
        ->assertSet('createdThread', '01JTHREADNEW00000000000000');

    // A new thread after the first one went through gets a key of its own.
    $component->call('startOver')
        ->set('title', 'Dark mode')
        ->set('body', 'Please add a dark mode.')
        ->call('submit');

    $keys = array_map(fn (Request $request): string => $request->header('Idempotency-Key')[0] ?? '', threadCreationRequests());

    expect($keys)->toHaveCount(3)
        ->and($keys[0])->not->toBe('')
        ->and($keys[1])->toBe($keys[0])
        ->and($keys[2])->not->toBe($keys[0]);
});

it('offers only the kinds the board allows', function (): void {
    fakeCommunityMember(['allowed_kinds' => ['discussion', 'bug']]);
    communityUser();

    Livewire::test(CommunityNewThread::class, ['board' => 'gurus'])
        ->assertSet('kind', 'discussion')
        ->assertSeeHtml('<option wire:key="chaosdesk-new-thread-kind-discussion" value="discussion">')
        ->assertSeeHtml('<option wire:key="chaosdesk-new-thread-kind-bug" value="bug">')
        ->assertDontSeeHtml('value="proposal"')
        ->set('kind', 'proposal')
        ->set('title', 'Dark mode')
        ->set('body', 'Please add a dark mode.')
        ->call('submit')
        ->assertHasErrors(['kind' => 'in']);

    expect(threadCreationRequests())->toBe([]);
});

it('shows a friendly message when a blocked member posts', function (): void {
    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/threads*' => Http::response([
            'message' => 'Raw API refusal text.',
            'error' => 'member_blocked',
        ], 403),
    ]);
    communityUser();

    Livewire::test(CommunityNewThread::class, ['board' => 'gurus'])
        ->set('kind', 'proposal')
        ->set('title', 'Dark mode')
        ->set('body', 'Please add a dark mode.')
        ->call('submit')
        ->assertSee('You can no longer post or vote on this board.')
        ->assertDontSee('Raw API refusal text.')
        ->assertSet('createdThread', null);
});

it('shows a friendly message when the charter has to be accepted first', function (): void {
    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/threads*' => Http::response([
            'message' => 'Raw API refusal text.',
            'error' => 'charter_not_accepted',
        ], 403),
    ]);
    communityUser();

    Livewire::test(CommunityNewThread::class, ['board' => 'gurus'])
        ->set('kind', 'proposal')
        ->set('title', 'Dark mode')
        ->set('body', 'Please add a dark mode.')
        ->call('submit')
        ->assertSee('Please accept the community charter first.')
        ->assertDontSee('Raw API refusal text.');
});

it('renders the charter until the member accepted it', function (): void {
    fakeChaosDeskCommunity();
    communityUser();

    Livewire::test(CommunityNewThread::class, ['board' => 'gurus'])
        ->assertSeeLivewire(CommunityCharter::class)
        ->assertDontSeeHtml('wire:submit="submit"');
});

it('keeps the board slug in every request', function (): void {
    fakeCommunityMember(['slug' => 'clients']);
    communityUser();

    Livewire::test(CommunityNewThread::class, ['board' => 'clients'])
        ->set('kind', 'proposal')
        ->set('title', 'Dark mode')
        ->set('body', 'Please add a dark mode.')
        ->call('submit');

    $requests = communityRequests();

    expect($requests)->not->toBeEmpty()
        ->and($requests->reject(fn (Request $request): bool => preg_match(
            '#^'.preg_quote(COMMUNITY_URL, '#').'/boards/clients(/|\?|$)#',
            $request->url(),
        ) === 1))->toBeEmpty();
});
