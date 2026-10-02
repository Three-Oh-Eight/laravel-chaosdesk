<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Livewire\CommunityCharter;
use ThreeOhEight\ChaosDesk\Livewire\CommunityPolls;

const POLL_ULID = '01JPOLL0000000000000000000';
const OPTION_A = '01JOPTIONA0000000000000000';
const OPTION_B = '01JOPTIONB0000000000000000';

/**
 * Stub the poll list with one poll; the trailing `?` keeps the stub off the
 * responses and results urls, which carry no query string.
 *
 * @param  array<string, mixed>  $poll
 * @return array<string, mixed>
 */
function pollListStub(array $poll = []): array
{
    return ['*/public/community/boards/*/polls?*' => Http::response(communityPage([communityPollPayload($poll)]))];
}

/**
 * The response request the component sent, if any.
 */
function pollResponseRequest(): ?Request
{
    return communityRequests()->first(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/polls/'.POLL_ULID.'/responses');
}

it('renders an open poll with its options and no tally', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->assertOk()
        ->assertSee('What should we build next?')
        ->assertSee('CSV export')
        ->assertSee('Dark mode')
        ->assertSee('Pick one answer.')
        ->assertSee('Send answer')
        ->assertSeeHtml('type="radio"')
        ->assertDontSeeHtml('type="checkbox"');

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/results'));
});

it('sends the one picked option of a single-choice poll with an idempotency key', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->set('answers.'.POLL_ULID, OPTION_B)
        ->call('respond', POLL_ULID)
        ->assertSet('error', null)
        ->assertSet('savedPoll', POLL_ULID)
        ->assertSet('answers.'.POLL_ULID, OPTION_B)
        ->assertSee('Thanks, your answer is saved.');

    $request = pollResponseRequest();

    expect($request)->not->toBeNull()
        ->and($request->data())->toBe(['options' => [OPTION_B]])
        ->and($request->header('Idempotency-Key')[0] ?? '')->not->toBe('');
});

it('sends only the first pick when a single-choice answer is tampered into a list', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->set('answers.'.POLL_ULID, [OPTION_A, OPTION_B])
        ->call('respond', POLL_ULID);

    expect(pollResponseRequest()?->data())->toBe(['options' => [OPTION_A]]);
});

it('sends every picked option of a multiple-choice poll', function (): void {
    fakeCommunityMember(overrides: pollListStub(['is_multiple_choice' => true]));
    communityUser();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->assertSee('Pick one or more answers.')
        ->assertSeeHtml('type="checkbox"')
        ->set('answers.'.POLL_ULID, [OPTION_A, OPTION_B, 'not-an-option'])
        ->call('respond', POLL_ULID)
        ->assertSet('error', null);

    expect(pollResponseRequest()?->data())->toBe(['options' => [OPTION_A, OPTION_B]]);
});

it('asks for an answer before sending anything', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->set('answers.'.POLL_ULID, 'not-an-option')
        ->call('respond', POLL_ULID)
        ->assertHasErrors(['answers.'.POLL_ULID])
        ->assertSee('Choose an answer first.');

    expect(pollResponseRequest())->toBeNull();
});

it('shows a friendly message when the poll closed in the meantime', function (): void {
    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/polls/*/responses' => Http::response([
            'message' => 'Raw API refusal text.',
            'error' => 'poll_closed',
        ], 403),
    ]);
    communityUser();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->set('answers.'.POLL_ULID, OPTION_A)
        ->call('respond', POLL_ULID)
        ->assertSet('savedPoll', null)
        ->assertSee('This poll has closed.')
        ->assertDontSee('Raw API refusal text.')
        ->assertDontSee('Thanks, your answer is saved.');
});

it('renders the results of a closed poll as bars', function (): void {
    fakeCommunityMember(overrides: pollListStub([
        'is_open' => false,
        'is_closed' => true,
        'closes_at' => '2026-09-30T12:00:00+00:00',
        'my_options' => [OPTION_A],
    ]));
    communityUser();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->assertOk()
        ->assertSee('Closed')
        ->assertSee('6 (75%)')
        ->assertSee('2 (25%)')
        ->assertSee('8 respondents')
        ->assertSeeHtml('style="width: 75%"')
        ->assertSeeHtml('style="width: 25%"')
        ->assertDontSeeHtml('type="radio"')
        ->assertDontSee('Send answer');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/polls/'.POLL_ULID.'/results');
});

it('shows a pending note when ChaosDesk still hides the results', function (): void {
    fakeCommunityMember(overrides: pollListStub(['is_open' => false, 'is_closed' => true]) + [
        '*/public/community/boards/*/polls/*/results' => Http::response([
            'message' => 'Raw API refusal text.',
            'error' => 'poll_results_hidden',
        ], 403),
    ]);
    communityUser();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->assertOk()
        ->assertSet('error', null)
        ->assertSee('Results are not available yet.')
        ->assertDontSee('Raw API refusal text.')
        ->assertDontSeeHtml('role="alert"');
});

it('renders the charter instead of the polls until the member accepted it', function (): void {
    fakeChaosDeskCommunity();
    communityUser();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->assertSeeLivewire(CommunityCharter::class)
        ->assertDontSee('What should we build next?');
});

it('asks a guest to sign in', function (): void {
    fakeCommunityMember();

    Livewire::test(CommunityPolls::class, ['board' => 'gurus'])
        ->assertSee('Sign in to take part in the community.')
        ->assertDontSee('What should we build next?');

    expect(communityRequests())->toBeEmpty();
});
