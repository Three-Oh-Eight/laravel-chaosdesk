<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Community\CommunityUrls;
use ThreeOhEight\ChaosDesk\Livewire\CommunityBoard;
use ThreeOhEight\ChaosDesk\Livewire\CommunityCharter;

afterEach(function (): void {
    CommunityUrls::resolveUsing(null);
});

/**
 * The query string of the last thread list request.
 *
 * @return array<string, mixed>
 */
function lastThreadListQuery(): array
{
    $request = communityRequests()
        ->filter(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/threads'))
        ->last();

    expect($request)->not->toBeNull();

    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

it('renders the board with its threads', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->assertOk()
        ->assertSee('Gurus')
        ->assertSee('Export invoices to CSV')
        ->assertSee('How do you plan your week?')
        ->assertSee('Page 1 of 2')
        ->assertSee('What should we build next?');

    expect(lastThreadListQuery())->toBe(['sort' => 'activity', 'page' => '1', 'per_page' => '20']);
});

it('puts the filters and the sort on the thread list request', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->set('kind', 'proposal')
        ->set('status', 'planned')
        ->set('sort', 'votes');

    expect(lastThreadListQuery())->toBe([
        'kind' => 'proposal',
        'status' => 'planned',
        'sort' => 'votes',
        'page' => '1',
        'per_page' => '20',
    ]);
});

it('drops unknown filters and resets to the first page when a filter changes', function (): void {
    fakeCommunityMember();
    communityUser();

    $component = Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->call('nextPage');

    expect(lastThreadListQuery()['page'])->toBe('2');

    $component->set('kind', 'rant')->set('sort', 'loudest');

    expect(lastThreadListQuery())->toBe(['sort' => 'activity', 'page' => '1', 'per_page' => '20']);
});

it('withdraws an upvote the member already gave', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->call('toggleVote', '01JTHREADPROPOSAL000000000')
        ->assertSet('error', null);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads/01JTHREADPROPOSAL000000000/vote');
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('upvotes a proposal the member has not voted on', function (): void {
    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/threads?*' => Http::response(communityPage([
            communityThreadPayload(['has_voted' => false, 'votes_count' => 6]),
        ])),
    ]);
    communityUser();

    Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->call('toggleVote', '01JTHREADPROPOSAL000000000')
        ->assertSet('error', null);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads/01JTHREADPROPOSAL000000000/vote');
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
});

it('renders the charter instead of the list until the member accepted it', function (): void {
    fakeChaosDeskCommunity();
    communityUser();

    Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->assertSeeLivewire(CommunityCharter::class)
        ->assertSee('Community charter')
        ->assertDontSee('Export invoices to CSV');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/threads'));
});

it('shows a friendly message when voting needs the charter first', function (): void {
    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/threads/*/vote' => Http::response([
            'message' => 'Raw API refusal text.',
            'error' => 'charter_not_accepted',
        ], 403),
    ]);
    communityUser();

    Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->call('toggleVote', '01JTHREADPROPOSAL000000000')
        ->assertSee('Please accept the community charter first.')
        ->assertDontSee('Raw API refusal text.');
});

it('shows a friendly message when a blocked member votes', function (): void {
    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/threads/*/vote' => Http::response([
            'message' => 'Raw API refusal text.',
            'error' => 'member_blocked',
        ], 403),
    ]);
    communityUser();

    Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->call('toggleVote', '01JTHREADPROPOSAL000000000')
        ->assertSee('You can no longer post or vote on this board.')
        ->assertDontSee('Raw API refusal text.');
});

it('links threads through the configured urls', function (): void {
    fakeCommunityMember();
    communityUser();

    CommunityUrls::resolveUsing(fn (string $page, string $board, ?string $thread): ?string => $page === CommunityUrls::THREAD
        ? "https://host.test/community/{$board}/{$thread}"
        : null);

    Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->assertSeeHtml('<a href="https://host.test/community/gurus/01JTHREADPROPOSAL000000000" class="hover:underline">');
});

it('keeps the board slug in every request', function (): void {
    fakeCommunityMember(['slug' => 'clients']);
    communityUser();

    Livewire::test(CommunityBoard::class, ['board' => 'clients'])
        ->set('kind', 'proposal')
        ->call('nextPage')
        ->call('toggleVote', '01JTHREADPROPOSAL000000000');

    $requests = communityRequests();

    expect($requests)->not->toBeEmpty()
        ->and($requests->reject(fn (Request $request): bool => preg_match(
            '#^'.preg_quote(COMMUNITY_URL, '#').'/boards/clients(/|\?|$)#',
            $request->url(),
        ) === 1))->toBeEmpty();
});
