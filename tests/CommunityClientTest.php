<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Community\CommunityClient;
use ThreeOhEight\ChaosDesk\Community\CommunityMemberClient;
use ThreeOhEight\ChaosDesk\Community\Data\Board;
use ThreeOhEight\ChaosDesk\Community\Data\Page;
use ThreeOhEight\ChaosDesk\Community\Data\Poll;
use ThreeOhEight\ChaosDesk\Community\Data\PollResults;
use ThreeOhEight\ChaosDesk\Community\Data\Post;
use ThreeOhEight\ChaosDesk\Community\Data\Thread;
use ThreeOhEight\ChaosDesk\Community\Member;
use ThreeOhEight\ChaosDesk\Community\ThreadKind;
use ThreeOhEight\ChaosDesk\Community\ThreadStatus;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Exceptions\CommunityException;
use ThreeOhEight\ChaosDesk\Facades\ChaosDesk as ChaosDeskFacade;
use ThreeOhEight\ChaosDesk\Tests\Support\PlainUser;
use ThreeOhEight\ChaosDesk\Tests\Support\User;

beforeEach(function (): void {
    config(['chaosdesk.sites.gurus' => ['token' => 'gurus-token']]);

    $this->member = new Member('user:7', 'Ada Lovelace', 'ada@example.test', 'nl');
    $this->community = app(ChaosDesk::class)->forSite('gurus')->community()->as($this->member);
});

it('lists the boards of the site with its token and no member header', function (): void {
    fakeChaosDeskCommunity();

    $client = ChaosDeskFacade::community();

    expect($client)->toBeInstanceOf(CommunityClient::class)
        ->and($client->site())->toBe('default');

    $boards = $client->boards();

    expect($boards)->toHaveCount(2)
        ->and($boards[0])->toBeInstanceOf(Board::class)
        ->and($boards[0]->slug)->toBe('gurus')
        ->and($boards[0]->charterVersion)->toBe(2)
        ->and($boards[0]->charter)->toBeNull()
        ->and($boards[0]->member)->toBeNull()
        ->and($boards[0]->needsCharterAcceptance())->toBeFalse()
        ->and($boards[1]->slug)->toBe('clients')
        ->and($boards[1]->allows(ThreadKind::Proposal))->toBeFalse()
        ->and($boards[1]->allows('discussion'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === COMMUNITY_URL.'/boards'
        && $request->hasHeader('X-Site-Token', 'test-site-token')
        && ! $request->hasHeader('X-Community-Member'));
});

it('uses the token of the site it was created for', function (): void {
    fakeChaosDeskCommunity();

    $this->community->board('gurus');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Site-Token', 'gurus-token'));
});

it('sends the member as base64 encoded json in the X-Community-Member header', function (): void {
    fakeChaosDeskCommunity();

    expect($this->community)->toBeInstanceOf(CommunityMemberClient::class)
        ->and($this->community->member())->toBe($this->member)
        ->and($this->community->site())->toBe('gurus');

    $this->community->board('gurus');

    Http::assertSent(function (Request $request): bool {
        $header = $request->header('X-Community-Member')[0] ?? '';

        return base64_decode($header, true) !== false
            && communityMemberSent($request) === [
                'external_id' => 'user:7',
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.test',
                'locale' => 'nl',
            ];
    });
});

it('keeps a non-ascii name intact and leaves out a missing locale', function (): void {
    fakeChaosDeskCommunity();

    app(ChaosDesk::class)->community()->as(new Member('42', 'Zoë Ørsted', 'zoe@example.test'))->board('gurus');

    Http::assertSent(fn (Request $request): bool => communityMemberSent($request) === [
        'external_id' => '42',
        'name' => 'Zoë Ørsted',
        'email' => 'zoe@example.test',
    ]);
});

it('builds the member from a user of the host application', function (): void {
    fakeChaosDeskCommunity();

    $user = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.test']);

    $client = app(ChaosDesk::class)->community()->as($user, 'de');

    expect($client->member()->externalId)->toBe('ext-'.$user->getKey())
        ->and($client->member()->locale)->toBe('de');

    $client->board('gurus');

    Http::assertSent(fn (Request $request): bool => communityMemberSent($request) === [
        'external_id' => 'ext-'.$user->getKey(),
        'name' => 'Ada',
        'email' => 'ada@example.test',
        'locale' => 'de',
    ]);
});

it('refuses a user without a name or email before any request', function (): void {
    $user = PlainUser::query()->create(['name' => 'No Email']);

    expect(fn () => app(ChaosDesk::class)->community()->as($user))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new Member('7', '', 'ada@example.test'))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('fetches a board with its charter and the member state', function (): void {
    fakeChaosDeskCommunity();

    $board = $this->community->board('gurus');

    expect($board->name)->toBe('Gurus')
        ->and($board->allowedKinds)->toBe(['discussion', 'proposal', 'bug'])
        ->and($board->charter?->markdown)->toBe("# Charter\n\nBe kind.")
        ->and($board->charter?->version)->toBe(2)
        ->and($board->charter?->accepted)->toBeFalse()
        ->and($board->needsCharterAcceptance())->toBeTrue()
        ->and($board->member?->name)->toBe('Ada')
        ->and($board->memberIsBlocked())->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === COMMUNITY_URL.'/boards/gurus');
});

it('accepts the charter version the member was shown', function (): void {
    fakeChaosDeskCommunity();

    $acceptance = $this->community->acceptCharter('gurus', 2);

    expect($acceptance->version)->toBe(2)
        ->and($acceptance->acceptedAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($acceptance->acceptedAt?->toIso8601String())->toBe('2026-10-02T10:00:00+00:00')
        ->and(idempotencyKeysSent()->first())->not->toBeEmpty();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/charter/accept'
        && $request->data() === ['version' => 2]);
});

it('accepts the current charter when no version is given', function (): void {
    fakeChaosDeskCommunity();

    $this->community->acceptCharter('gurus');

    Http::assertSent(fn (Request $request): bool => $request->url() === COMMUNITY_URL.'/boards/gurus/charter/accept'
        && $request->data() === []);
});

it('lists threads with filters, sort and paging as query parameters', function (): void {
    fakeChaosDeskCommunity();

    $page = $this->community->threads(
        'gurus',
        ['kind' => ThreadKind::Proposal, 'status' => 'planned', 'sort' => 'votes'],
        page: 2,
        perPage: 10,
    );

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->method() === 'GET'
            && str_starts_with($request->url(), COMMUNITY_URL.'/boards/gurus/threads?')
            && $query === ['kind' => 'proposal', 'status' => 'planned', 'sort' => 'votes', 'page' => '2', 'per_page' => '10'];
    });

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page)->toHaveCount(2)
        ->and($page->total)->toBe(23)
        ->and($page->lastPage)->toBe(2)
        ->and($page->perPage)->toBe(20)
        ->and($page->hasMorePages())->toBeTrue()
        ->and($page->items[0])->toBeInstanceOf(Thread::class);

    $proposal = $page->items[0];

    expect($proposal->ulid)->toBe('01JTHREADPROPOSAL000000000')
        ->and($proposal->isKind(ThreadKind::Proposal))->toBeTrue()
        ->and($proposal->hasStatus(ThreadStatus::Planned))->toBeTrue()
        ->and($proposal->votesCount)->toBe(7)
        ->and($proposal->postsCount)->toBe(2)
        ->and($proposal->acceptsVotes)->toBeTrue()
        ->and($proposal->isPinned)->toBeTrue()
        ->and($proposal->hasVoted)->toBeTrue()
        ->and($proposal->isMine)->toBeFalse()
        ->and($proposal->authorName)->toBe('Grace')
        ->and($proposal->posts)->toBe([])
        ->and($proposal->lastActivityAt?->toIso8601String())->toBe('2026-10-02T08:30:00+00:00')
        ->and($page->items[1]->isKind('discussion'))->toBeTrue()
        ->and($page->items[1]->hasVoted)->toBeFalse();
});

it('leaves empty filters out of the query', function (): void {
    fakeChaosDeskCommunity();

    $this->community->threads('gurus', ['kind' => null, 'status' => '']);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query === ['page' => '1', 'per_page' => '20'];
    });
});

it('fetches a thread with its replies', function (): void {
    fakeChaosDeskCommunity();

    $thread = $this->community->thread('gurus', '01JTHREADPROPOSAL000000000');

    expect($thread->title)->toBe('Export invoices to CSV')
        ->and($thread->acceptsReplies())->toBeTrue()
        ->and($thread->posts)->toHaveCount(2)
        ->and($thread->posts[0])->toBeInstanceOf(Post::class)
        ->and($thread->posts[0]->authorName)->toBe('Linus')
        ->and($thread->posts[0]->isAgent)->toBeFalse()
        ->and($thread->posts[1]->isOfficial)->toBeTrue()
        ->and($thread->posts[1]->isAgent)->toBeTrue()
        ->and($thread->posts[1]->authorName)->toBe('Styn');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads/01JTHREADPROPOSAL000000000');
});

it('creates a thread with the given idempotency key', function (): void {
    fakeChaosDeskCommunity();

    $thread = $this->community->createThread('gurus', ThreadKind::Proposal, 'Dark mode', 'Please add a dark mode.', 'form-123');

    expect($thread->ulid)->toBe('01JTHREADNEW00000000000000')
        ->and($thread->isMine)->toBeTrue()
        ->and($thread->hasVoted)->toBeFalse()
        ->and($thread->votesCount)->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads'
        && $request->hasHeader('Idempotency-Key', 'form-123')
        && $request->data() === ['kind' => 'proposal', 'title' => 'Dark mode', 'body' => 'Please add a dark mode.']);
});

it('sends a fresh idempotency key per write when none is given', function (): void {
    fakeChaosDeskCommunity();

    $this->community->createThread('gurus', 'discussion', 'One', 'Body.');
    $this->community->reply('gurus', '01JTHREADPROPOSAL000000000', 'Two.');
    $this->community->vote('gurus', '01JTHREADPROPOSAL000000000');
    $this->community->respond('gurus', '01JPOLL0000000000000000000', ['01JOPTIONA0000000000000000']);

    expect(idempotencyKeysSent()->filter()->unique())->toHaveCount(4);
});

it('replies to a thread', function (): void {
    fakeChaosDeskCommunity();

    $post = $this->community->reply('gurus', '01JTHREADPROPOSAL000000000', 'Same here.');

    expect($post->ulid)->toBe('01JPOSTNEW0000000000000000')
        ->and($post->body)->toBe('Same here.')
        ->and($post->isMine)->toBeTrue()
        ->and($post->createdAt)->toBeInstanceOf(CarbonImmutable::class);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads/01JTHREADPROPOSAL000000000/posts'
        && $request->data() === ['body' => 'Same here.']);
});

it('votes with a POST and withdraws with a DELETE', function (): void {
    fakeChaosDeskCommunity();

    $vote = $this->community->vote('gurus', '01JTHREADPROPOSAL000000000');
    $withdrawn = $this->community->unvote('gurus', '01JTHREADPROPOSAL000000000');

    expect($vote->threadUlid)->toBe('01JTHREADPROPOSAL000000000')
        ->and($vote->votesCount)->toBe(7)
        ->and($vote->hasVoted)->toBeTrue()
        ->and($withdrawn->votesCount)->toBe(6)
        ->and($withdrawn->hasVoted)->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads/01JTHREADPROPOSAL000000000/vote');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/threads/01JTHREADPROPOSAL000000000/vote');
});

it('lists polls with the member answer and no tally', function (): void {
    fakeChaosDeskCommunity();

    $page = $this->community->polls('gurus', perPage: 5);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/polls?page=1&per_page=5');

    $poll = $page->items[0];

    expect($poll)->toBeInstanceOf(Poll::class)
        ->and($poll->question)->toBe('What should we build next?')
        ->and($poll->isOpen)->toBeTrue()
        ->and($poll->isClosed)->toBeFalse()
        ->and($poll->opensAt)->toBeNull()
        ->and($poll->closesAt?->toIso8601String())->toBe('2026-10-09T12:00:00+00:00')
        ->and($poll->threadUlid)->toBe('01JTHREADPROPOSAL000000000')
        ->and($poll->options)->toHaveCount(2)
        ->and($poll->options[0]->label)->toBe('CSV export')
        ->and($poll->options[0]->responsesCount)->toBeNull()
        ->and($poll->hasResponded())->toBeFalse();
});

it('answers a poll with options or their ulids', function (): void {
    fakeChaosDeskCommunity();

    $poll = Poll::fromArray(communityPollPayload());

    $answered = $this->community->respond('gurus', $poll->ulid, [$poll->options[1]]);

    expect($answered->hasResponded())->toBeTrue()
        ->and($answered->picked($poll->options[1]))->toBeTrue()
        ->and($answered->picked('01JOPTIONA0000000000000000'))->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/polls/01JPOLL0000000000000000000/responses'
        && $request->data() === ['options' => ['01JOPTIONB0000000000000000']]);
});

it('fetches the results of a closed poll', function (): void {
    fakeChaosDeskCommunity();

    $results = $this->community->pollResults('gurus', '01JPOLL0000000000000000000');

    expect($results)->toBeInstanceOf(PollResults::class)
        ->and($results->respondentsCount)->toBe(8)
        ->and($results->options[0]->responsesCount)->toBe(6)
        ->and($results->options[1]->responsesCount)->toBe(2)
        ->and($results->percentage($results->options[0]))->toBe(75.0);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === COMMUNITY_URL.'/boards/gurus/polls/01JPOLL0000000000000000000/results');
});

it('addresses every call to the board it names', function (): void {
    fakeChaosDeskCommunity();

    $this->community->threads('clients');
    $this->community->thread('clients', '01JTHREADPROPOSAL000000000');
    $this->community->polls('clients');

    expect(Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->all())->each->toStartWith(COMMUNITY_URL.'/boards/clients/');
});

it('encodes slugs and ulids so they cannot step outside their board', function (): void {
    fakeChaosDeskCommunity();

    $this->community->thread('gurus', '../../clients/threads/01JX');
    $this->community->board('gurus/../clients');

    Http::assertSent(fn (Request $request): bool => $request->url() === COMMUNITY_URL.'/boards/gurus/threads/..%2F..%2Fclients%2Fthreads%2F01JX');
    Http::assertSent(fn (Request $request): bool => $request->url() === COMMUNITY_URL.'/boards/gurus%2F..%2Fclients');
});

it('refuses an empty or dot slug or ulid before any request', function (string $board, string $thread): void {
    expect(fn () => $this->community->thread($board, $thread))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'empty board' => ['', '01JX'],
    'dot-dot board' => ['..', '01JX'],
    'empty thread' => ['gurus', ' '],
    'dot thread' => ['gurus', '.'],
]);

it('maps a coded refusal onto a community exception', function (int $status, string $code, ?string $predicate): void {
    fakeChaosDeskCommunity(['*' => Http::response(['error' => $code, 'message' => 'Refused.'], $status)]);

    try {
        $this->community->reply('gurus', '01JTHREADPROPOSAL000000000', 'Hello.');
        $this->fail('Expected a CommunityException.');
    } catch (CommunityException $e) {
        expect($e)->toBeInstanceOf(ChaosDeskException::class)
            ->and($e->errorCode)->toBe($code)
            ->and($e->status)->toBe($status)
            ->and($e->getMessage())->toBe('Refused.')
            ->and($e->is($code))->toBeTrue()
            ->and($e->is('some_other_code'))->toBeFalse();

        if ($predicate !== null) {
            expect($e->{$predicate}())->toBeTrue();
        }
    }
})->with([
    'charter not accepted' => [403, CommunityException::CHARTER_NOT_ACCEPTED, 'requiresCharterAcceptance'],
    'charter version mismatch' => [409, CommunityException::CHARTER_VERSION_MISMATCH, 'requiresCharterAcceptance'],
    'member blocked' => [403, CommunityException::MEMBER_BLOCKED, 'isMemberBlocked'],
    'thread locked' => [403, CommunityException::THREAD_LOCKED, 'isThreadLocked'],
    'poll closed' => [403, CommunityException::POLL_CLOSED, 'isPollUnavailable'],
    'poll not open' => [403, CommunityException::POLL_NOT_OPEN, 'isPollUnavailable'],
    'poll results hidden' => [403, CommunityException::POLL_RESULTS_HIDDEN, 'isPollUnavailable'],
    'board not found' => [404, CommunityException::BOARD_NOT_FOUND, 'isNotFound'],
    'thread not found' => [404, CommunityException::THREAD_NOT_FOUND, 'isNotFound'],
    'poll not found' => [404, CommunityException::POLL_NOT_FOUND, 'isNotFound'],
    'thread not votable' => [422, CommunityException::THREAD_NOT_VOTABLE, 'isValidationError'],
    'identity conflict' => [409, CommunityException::MEMBER_IDENTITY_CONFLICT, null],
    'code a newer ChaosDesk adds' => [403, 'board_archived', null],
]);

it('surfaces poll results hidden while the poll runs', function (): void {
    fakeChaosDeskCommunity(['*/results' => Http::response([
        'error' => 'poll_results_hidden',
        'message' => 'Poll results are shown once the poll has closed.',
    ], 403)]);

    expect(fn () => $this->community->pollResults('gurus', '01JPOLL0000000000000000000'))
        ->toThrow(function (CommunityException $e): void {
            expect($e->errorCode)->toBe(CommunityException::POLL_RESULTS_HIDDEN)
                ->and($e->isUnauthorised())->toBeTrue()
                ->and($e->requiresCharterAcceptance())->toBeFalse();
        });
});

it('keeps a refusal without a machine code a plain ChaosDesk exception', function (int $status, array $body, string $predicate): void {
    fakeChaosDeskCommunity(['*' => Http::response($body, $status)]);

    try {
        $this->community->board('gurus');
        $this->fail('Expected a ChaosDeskException.');
    } catch (ChaosDeskException $e) {
        expect($e)->not->toBeInstanceOf(CommunityException::class)
            ->and($e->{$predicate}())->toBeTrue();
    }
})->with([
    'bad site token' => [401, ['error' => 'Site token required', 'message' => 'Please provide a valid X-Site-Token header.'], 'isUnauthorised'],
    'member header invalid' => [422, ['message' => 'The member.email field is required.', 'errors' => ['member.email' => ['The member.email field is required.']]], 'isValidationError'],
    'server error' => [500, ['message' => 'Server Error'], 'isUnavailable'],
]);

it('throws before any request when the site has no token', function (): void {
    expect(fn () => app(ChaosDesk::class)->forSite('nowhere')->community()->as($this->member)->board('gurus'))
        ->toThrow(ChaosDeskException::class, 'chaosdesk.sites.nowhere.token');

    Http::assertNothingSent();
});

it('round-trips every data object through toArray', function (): void {
    $board = communityBoardPayload();
    $thread = communityThreadPayload(['posts' => [communityPostPayload()]]);
    $poll = communityPollPayload(['my_options' => ['01JOPTIONA0000000000000000']]);
    $results = communityPollResultsPayload();

    expect(Board::fromArray($board)->toArray())->toEqual($board)
        ->and(Board::fromArray(Board::fromArray($board)->toArray()))->toEqual(Board::fromArray($board))
        ->and(Thread::fromArray($thread)->toArray())->toEqual($thread)
        ->and(Poll::fromArray($poll)->toArray())->toEqual($poll)
        ->and(PollResults::fromArray($results)->toArray())->toEqual($results);
});
