<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Community\CommunityUrls;
use ThreeOhEight\ChaosDesk\Livewire\CommunityCharter;

afterEach(function (): void {
    CommunityUrls::resolveUsing(null);
});

/**
 * Fake a board whose charter the member has not accepted yet.
 *
 * @param  array<string, mixed>  $charter
 * @param  array<string, mixed>  $overrides
 */
function fakeUnacceptedCharter(array $charter = [], array $overrides = []): void
{
    fakeCommunityMember(['charter' => $charter + ['markdown' => "# Charter\n\nBe kind.", 'version' => 2, 'accepted' => false]], $overrides);
}

/**
 * The charter accept requests the component sent, in order.
 *
 * @return list<Request>
 */
function charterAcceptRequests(): array
{
    return communityRequests()
        ->filter(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === COMMUNITY_URL.'/boards/gurus/charter/accept')
        ->values()
        ->all();
}

it('renders the charter markdown with raw html escaped', function (): void {
    fakeUnacceptedCharter([
        'markdown' => "# House rules\n\nBe **kind**.\n\n<script>alert('pwned')</script>\n\n[Click](javascript:alert(1))",
    ]);
    communityUser();

    Livewire::test(CommunityCharter::class, ['board' => 'gurus'])
        ->assertOk()
        ->assertSee('Community charter')
        ->assertSee('Version 2')
        ->assertSeeHtml('<h1>House rules</h1>')
        ->assertSeeHtml('<strong>kind</strong>')
        ->assertSeeHtml('&lt;script&gt;')
        ->assertDontSeeHtml('<script>alert')
        ->assertDontSeeHtml('javascript:alert')
        ->assertSee('I accept the charter');
});

it('accepts the version the member was shown', function (): void {
    fakeUnacceptedCharter(['version' => 4]);
    communityUser();

    Livewire::test(CommunityCharter::class, ['board' => 'gurus'])
        ->assertSet('version', 4)
        ->call('accept')
        ->assertSet('error', null)
        ->assertSet('accepted', true)
        ->assertDispatched('chaosdesk-community-charter-accepted', board: 'gurus')
        ->assertSee('Thanks, you accepted the charter.')
        ->assertDontSee('I accept the charter');

    $requests = charterAcceptRequests();

    expect($requests)->toHaveCount(1)
        ->and($requests[0]->data())->toBe(['version' => 4])
        ->and($requests[0]->header('Idempotency-Key')[0] ?? '')->not->toBe('');
});

it('reloads the new charter and asks again when it changed while the member read it', function (): void {
    $serverVersion = 2;

    fakeChaosDeskCommunity([
        '*/public/community/boards/*/charter/accept' => function (Request $request) use (&$serverVersion) {
            return $request['version'] === $serverVersion
                ? Http::response(['message' => 'Charter accepted.', 'charter' => ['version' => $serverVersion, 'accepted' => true, 'accepted_at' => '2026-10-02T10:00:00+00:00']])
                : Http::response(['message' => 'Raw API refusal text.', 'error' => 'charter_version_mismatch'], 409);
        },
        '*/public/community/boards/*' => function (Request $request) use (&$serverVersion) {
            if (preg_match('#/boards/[^/]+$#', (string) parse_url($request->url(), PHP_URL_PATH)) !== 1) {
                return null;
            }

            return Http::response(['data' => communityBoardPayload([
                'charter_version' => $serverVersion,
                'charter' => [
                    'markdown' => $serverVersion === 2 ? 'Be kind.' : 'Be kind and stay on topic.',
                    'version' => $serverVersion,
                    'accepted' => false,
                ],
            ])]);
        },
    ]);
    communityUser();

    $component = Livewire::test(CommunityCharter::class, ['board' => 'gurus'])
        ->assertSet('version', 2);

    $serverVersion = 3;

    $component->call('accept')
        ->assertSet('accepted', false)
        ->assertSet('version', 3)
        ->assertSee('The charter changed while you were reading. Please read the new version and accept it again.')
        ->assertSee('Be kind and stay on topic.')
        ->assertSee('Version 3')
        ->assertSee('I accept the charter')
        ->assertDontSee('Raw API refusal text.')
        ->assertNotDispatched('chaosdesk-community-charter-accepted');

    $component->call('accept')
        ->assertSet('error', null)
        ->assertSet('accepted', true)
        ->assertDispatched('chaosdesk-community-charter-accepted', board: 'gurus');

    expect(array_map(fn (Request $request): mixed => $request['version'], charterAcceptRequests()))->toBe([2, 3]);
});

it('links on to the board once accepted when the host routes it', function (): void {
    fakeUnacceptedCharter();
    communityUser();

    CommunityUrls::resolveUsing(fn (string $page, string $board): ?string => $page === CommunityUrls::BOARD
        ? "https://host.test/community/{$board}"
        : null);

    Livewire::test(CommunityCharter::class, ['board' => 'gurus'])
        ->call('accept')
        ->assertSee('Go to the board')
        ->assertSeeHtml('href="https://host.test/community/gurus"');
});

it('leaves navigation to the board when embedded', function (): void {
    fakeUnacceptedCharter();
    communityUser();

    CommunityUrls::resolveUsing(fn (): string => 'https://host.test/community');

    Livewire::test(CommunityCharter::class, ['board' => 'gurus', 'embedded' => true])
        ->call('accept')
        ->assertDispatched('chaosdesk-community-charter-accepted', board: 'gurus')
        ->assertSee('Thanks, you accepted the charter.')
        ->assertDontSee('Go to the board');
});

it('tells a member who already accepted the current version', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityCharter::class, ['board' => 'gurus'])
        ->assertSee('You accepted this version of the charter.')
        ->assertDontSee('I accept the charter');
});

it('shows a friendly message when a blocked member accepts', function (): void {
    fakeUnacceptedCharter(overrides: [
        '*/public/community/boards/*/charter/accept' => Http::response([
            'message' => 'Raw API refusal text.',
            'error' => 'member_blocked',
        ], 403),
    ]);
    communityUser();

    Livewire::test(CommunityCharter::class, ['board' => 'gurus'])
        ->call('accept')
        ->assertSet('accepted', false)
        ->assertSee('You can no longer post or vote on this board.')
        ->assertDontSee('Raw API refusal text.')
        ->assertNotDispatched('chaosdesk-community-charter-accepted');
});

it('asks a guest to sign in and refuses to accept for them', function (): void {
    fakeUnacceptedCharter();

    Livewire::test(CommunityCharter::class, ['board' => 'gurus'])
        ->assertSee('Sign in to take part in the community.')
        ->call('accept')
        ->assertSet('accepted', false)
        ->assertNotDispatched('chaosdesk-community-charter-accepted');

    expect(communityRequests())->toBeEmpty();
});
