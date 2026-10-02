<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Community\Member;
use ThreeOhEight\ChaosDesk\Livewire\CommunityBoard;
use ThreeOhEight\ChaosDesk\Livewire\CommunityCharter;
use ThreeOhEight\ChaosDesk\Livewire\CommunityNewThread;
use ThreeOhEight\ChaosDesk\Livewire\CommunityPolls;
use ThreeOhEight\ChaosDesk\Livewire\CommunityThread;

const SECURITY_THREAD = '01JTHREADPROPOSAL000000000';

it('refuses a browser update to an identity-bearing prop', function (string $component, array $params, string $property, mixed $value): void {
    fakeCommunityMember();
    communityUser();

    expect(fn () => Livewire::test($component, $params)->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'board on the board' => [CommunityBoard::class, ['board' => 'gurus'], 'board', 'clients'],
    'site on the board' => [CommunityBoard::class, ['board' => 'gurus'], 'site', 'other'],
    'open thread on the board' => [CommunityBoard::class, ['board' => 'gurus'], 'openThread', SECURITY_THREAD],
    'board on the thread' => [CommunityThread::class, ['board' => 'gurus', 'thread' => SECURITY_THREAD], 'board', 'clients'],
    'thread on the thread' => [CommunityThread::class, ['board' => 'gurus', 'thread' => SECURITY_THREAD], 'thread', '01JTHREADDISCUSSION0000000'],
    'board on the new thread form' => [CommunityNewThread::class, ['board' => 'gurus'], 'board', 'clients'],
    'board on the polls' => [CommunityPolls::class, ['board' => 'gurus'], 'board', 'clients'],
    'board on the charter' => [CommunityCharter::class, ['board' => 'gurus'], 'board', 'clients'],
    'charter version' => [CommunityCharter::class, ['board' => 'gurus'], 'version', 99],
]);

it('never puts the site token or the member header in the html or the snapshot', function (string $component, array $params): void {
    fakeCommunityMember();
    $user = communityUser();

    $testable = Livewire::test($component, $params);
    $rendered = $testable->html().json_encode($testable->snapshot);

    expect($rendered)
        ->not->toContain('test-site-token')
        ->not->toContain(Member::fromUser($user)->header())
        ->not->toContain('X-Community-Member');
})->with([
    'board' => [CommunityBoard::class, ['board' => 'gurus']],
    'thread' => [CommunityThread::class, ['board' => 'gurus', 'thread' => SECURITY_THREAD]],
    'new thread' => [CommunityNewThread::class, ['board' => 'gurus']],
    'polls' => [CommunityPolls::class, ['board' => 'gurus']],
    'charter' => [CommunityCharter::class, ['board' => 'gurus']],
]);

it('keeps a crafted thread id from the browser away from the api', function (string $method, string $thread): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityBoard::class, ['board' => 'gurus'])
        ->call($method, $thread)
        ->assertOk()
        ->assertSet('openThread', null)
        ->assertSet('screen', 'list')
        ->assertSee('could not be found');

    expect(communityRequests()->filter(fn (Request $request): bool => in_array($request->method(), ['POST', 'DELETE'], true)
        || str_contains($request->url(), '/threads/')))->toBeEmpty();
})->with([
    'vote on a parent path' => ['toggleVote', '../../clients/threads/01JTHREADPROPOSAL000000000'],
    'vote on dot dot' => ['toggleVote', '..'],
    'open a parent path' => ['showThread', '../clients'],
    'open dot dot' => ['showThread', '..'],
    'open an empty id' => ['showThread', ' '],
]);

it('answers 404 for a thread id that is not a ulid', function (): void {
    fakeCommunityMember();
    communityUser();

    Livewire::test(CommunityThread::class, ['board' => 'gurus', 'thread' => '..'])
        ->assertStatus(404);

    expect(communityRequests())->toBeEmpty();
});

it('escapes thread, reply and poll text from members', function (): void {
    fakeCommunityMember(overrides: [
        '*/public/community/boards/*/threads/*' => Http::response(['data' => communityThreadPayload([
            'title' => '<script>alert("title")</script>',
            'body' => '<img src=x onerror=alert("body")>',
            'posts' => [communityPostPayload(['body' => '<script>alert("post")</script>'])],
        ])]),
    ]);
    communityUser();

    Livewire::test(CommunityThread::class, ['board' => 'gurus', 'thread' => SECURITY_THREAD])
        ->assertDontSeeHtml('<script>alert')
        ->assertDontSeeHtml('<img src=x')
        ->assertSeeHtml('&lt;script&gt;alert(&quot;post&quot;)&lt;/script&gt;');
});
