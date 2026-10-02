<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use ThreeOhEight\ChaosDesk\Tests\Support\User;
use ThreeOhEight\ChaosDesk\Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in(__DIR__);

/**
 * The Community API base url the test configuration points at.
 */
const COMMUNITY_URL = 'https://chaosdesk.test/api/v1/public/community';

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
 * Fake the ChaosDesk agent API on top of the ingest API.
 *
 * Specific patterns are registered before broad ones because the first
 * matching stub wins; overrides go first for the same reason.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeChaosDeskAgent(array $overrides = []): void
{
    Http::fake($overrides + [
        '*/api/v1/tickets/*/messages*' => Http::response([
            'message' => 'Reply added successfully.',
            'reply' => agentMessagePayload(),
        ], 201),
        '*/api/v1/tickets/*/notes*' => Http::response([
            'message' => 'Internal note added successfully.',
            'note' => agentMessagePayload(['is_internal' => true, 'body' => 'Checked the billing log.']),
        ], 201),
        '*/api/v1/tickets/*' => Http::response(['data' => agentTicketPayload() + [
            'messages' => [
                agentMessagePayload([
                    'id' => 10,
                    'ulid' => '01JMSGCUSTOMER00000000000A',
                    'body' => 'It reverts every time.',
                    'is_agent' => false,
                    'agent_name' => null,
                    'author' => agentAuthorPayload(),
                ]),
                agentMessagePayload(),
            ],
            'attachments' => [],
        ]]),
        '*/api/v1/tickets*' => Http::response(agentTicketListResponse()),
        '*/api/v1/sites/*/agents*' => Http::response(['data' => [
            ['id' => 3, 'name' => 'Styn', 'email' => 'styn@example.test', 'role' => 'owner'],
            ['id' => 4, 'name' => 'Christoph', 'email' => 'christoph@example.test', 'role' => 'member'],
        ]]),
        '*/api/v1/sites/*/categories*' => Http::response(['data' => [agentCategoryPayload()]]),
        '*/api/v1/sites/*/priorities*' => Http::response(['data' => [agentPriorityPayload()]]),
        '*/api/v1/sites*' => Http::response([
            'data' => [
                ['id' => 2, 'ulid' => '01JSITECUSTOMERS0000000000', 'name' => 'Customers', 'slug' => 'customers'],
                ['id' => 3, 'ulid' => '01JSITEGURUS00000000000000', 'name' => 'Gurus', 'slug' => 'gurus'],
            ],
            'links' => ['first' => 'https://chaosdesk.test/api/v1/sites?page=1', 'last' => 'https://chaosdesk.test/api/v1/sites?page=1', 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 2],
        ]),
        '*/api/v1/authors/anonymise' => Http::response([
            'message' => 'Author anonymised.',
            'author' => agentAuthorPayload([
                'name' => 'Anonymised',
                'email' => 'anonymised+5@anonymised.invalid',
                'anonymised_at' => '2026-09-11T10:00:00+00:00',
            ]),
            'tickets_scrubbed' => 1,
        ]),
        '*/api/v1/categories*' => Http::response(['data' => [agentCategoryPayload()]]),
        '*/api/v1/priorities*' => Http::response(['data' => [agentPriorityPayload()]]),
    ]);

    fakeChaosDesk();
}

/**
 * A TicketListItem as the agent API returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agentTicketPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 41,
        'ulid' => '01JABCDEFGHIJKLMNOPQRSTUVW',
        'subject' => 'Cannot save my settings',
        'status' => 'open',
        'is_test' => false,
        'tags' => ['in-session', 'complaint'],
        'custom_fields' => null,
        'metadata' => ['extra' => ['chat_id' => 12, 'channel' => 'call']],
        'first_response_at' => null,
        'resolved_at' => null,
        'closed_at' => null,
        'sla' => [
            'first_response_due_at' => '2026-09-11T12:00:00+00:00',
            'first_response_warned_at' => null,
            'first_response_breached_at' => null,
            'resolution_due_at' => '2026-09-12T09:00:00+00:00',
            'resolution_warned_at' => null,
            'resolution_breached_at' => null,
            'paused_at' => null,
            'paused_minutes' => 0,
        ],
        'created_at' => '2026-09-11T09:00:00+00:00',
        'updated_at' => '2026-09-11T09:30:00+00:00',
        'author' => agentAuthorPayload(),
        'category' => agentCategoryPayload(),
        'priority' => agentPriorityPayload(),
        'assignee' => ['id' => 3, 'name' => 'Styn', 'email' => 'styn@example.test'],
    ], $overrides);
}

/**
 * A paginated ticket list as the agent API returns it.
 *
 * @param  list<array<string, mixed>>|null  $tickets
 * @return array<string, mixed>
 */
function agentTicketListResponse(?array $tickets = null): array
{
    if ($tickets === null) {
        $second = agentTicketPayload(['id' => 42, 'ulid' => '01JABCDEFGHIJKLMNOPQRSTUVX', 'status' => 'pending']);
        $second['tags'] = [];
        unset($second['assignee']);

        $tickets = [agentTicketPayload(), $second];
    }

    return [
        'data' => $tickets,
        'links' => [
            'first' => 'https://chaosdesk.test/api/v1/tickets?page=1',
            'last' => 'https://chaosdesk.test/api/v1/tickets?page=1',
            'prev' => null,
            'next' => null,
        ],
        'meta' => [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => count($tickets),
        ],
    ];
}

/**
 * A ticket message as the agent API returns it; an agent reply by default.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agentMessagePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 11,
        'ulid' => '01JMSGAGENT000000000000000',
        'body' => 'We are looking into it.',
        'body_format' => 'text',
        'is_internal' => false,
        'is_automated' => false,
        'is_agent' => true,
        'agent_name' => 'Styn',
        'created_at' => '2026-09-11T09:30:00+00:00',
        'attachments' => [],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agentAuthorPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 5,
        'external_id' => 'user:7',
        'name' => 'Ada',
        'email' => 'ada@example.test',
        'is_verified' => true,
        'anonymised_at' => null,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agentCategoryPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 8,
        'name' => 'Session issue',
        'slug' => 'session-issue',
        'color' => '#f59e0b',
        'description' => null,
        'sort_order' => 1,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agentPriorityPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 2,
        'name' => 'Normal',
        'slug' => 'normal',
        'color' => '#3b82f6',
        'level' => 2,
        'sla_hours' => 24,
        'is_default' => true,
    ], $overrides);
}

/**
 * The Idempotency-Key header of every request the fake recorded, in order.
 *
 * @return Collection<int, string>
 */
function idempotencyKeysSent(): Collection
{
    return Http::recorded()->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0] ?? '');
}

/**
 * Fake the ChaosDesk Community API, with payloads shaped like its resources.
 *
 * Specific patterns come first because the first matching stub wins; the
 * vote stub answers has_voted true for POST and false for DELETE, the
 * threads stub a created thread for POST and the list for GET.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeChaosDeskCommunity(array $overrides = []): void
{
    Http::fake($overrides + [
        '*/public/community/boards/*/charter/accept' => Http::response([
            'message' => 'Charter accepted.',
            'charter' => ['version' => 2, 'accepted' => true, 'accepted_at' => '2026-10-02T10:00:00+00:00'],
        ]),
        '*/public/community/boards/*/threads/*/posts' => Http::response([
            'message' => 'Reply added successfully.',
            'post' => communityPostPayload(['ulid' => '01JPOSTNEW0000000000000000', 'body' => 'Same here.', 'is_mine' => true]),
        ], 201),
        '*/public/community/boards/*/threads/*/vote' => fn (Request $request) => Http::response([
            'message' => $request->method() === 'DELETE' ? 'Vote withdrawn.' : 'Vote recorded.',
            'vote' => [
                'thread_ulid' => '01JTHREADPROPOSAL000000000',
                'votes_count' => $request->method() === 'DELETE' ? 6 : 7,
                'has_voted' => $request->method() !== 'DELETE',
            ],
        ]),
        '*/public/community/boards/*/threads/*' => Http::response(['data' => communityThreadPayload([
            'posts' => [
                communityPostPayload(),
                communityPostPayload([
                    'ulid' => '01JPOSTAGENT00000000000000',
                    'body' => 'This is planned for the next release.',
                    'is_official' => true,
                    'is_agent' => true,
                    'author' => ['name' => 'Styn'],
                ]),
            ],
        ])]),
        '*/public/community/boards/*/threads*' => fn (Request $request) => $request->method() === 'POST'
            ? Http::response([
                'message' => 'Thread created successfully.',
                'thread' => communityThreadPayload([
                    'ulid' => '01JTHREADNEW00000000000000',
                    'title' => 'Dark mode',
                    'body' => 'Please add a dark mode.',
                    'votes_count' => 0,
                    'posts_count' => 0,
                    'is_mine' => true,
                ], withVote: false),
            ], 201)
            : Http::response(communityPage([
                communityThreadPayload(),
                communityThreadPayload([
                    'ulid' => '01JTHREADDISCUSSION0000000',
                    'kind' => 'discussion',
                    'title' => 'How do you plan your week?',
                    'votes_count' => 0,
                    'accepts_votes' => false,
                    'has_voted' => false,
                ]),
            ], total: 23, lastPage: 2)),
        '*/public/community/boards/*/polls/*/responses' => Http::response([
            'message' => 'Response recorded.',
            'poll' => communityPollPayload(['my_options' => ['01JOPTIONB0000000000000000']]),
        ]),
        '*/public/community/boards/*/polls/*/results' => Http::response(['data' => communityPollResultsPayload()]),
        '*/public/community/boards/*/polls*' => Http::response(communityPage([communityPollPayload()])),
        '*/public/community/boards/*' => Http::response(['data' => communityBoardPayload()]),
        '*/public/community/boards' => Http::response(['data' => [
            communityBoardPayload(member: false),
            communityBoardPayload(['slug' => 'clients', 'name' => 'Clients', 'allowed_kinds' => ['discussion']], member: false),
        ]]),
    ]);
}

/**
 * Fake the Community API for a member who accepted the board's charter.
 *
 * The board stub only answers the board itself (config, charter, member
 * status) and returns null for anything below it, so threads, posts, votes
 * and polls fall through to the fakeChaosDeskCommunity() stubs. It is
 * registered first, ahead of those. Pass board fields to override (a
 * blocked member, other allowed kinds) and stubs that take precedence.
 *
 * @param  array<string, mixed>  $board
 * @param  array<string, mixed>  $overrides
 */
function fakeCommunityMember(array $board = [], array $overrides = []): void
{
    $payload = communityBoardPayload(array_replace([
        'charter' => ['markdown' => "# Charter\n\nBe kind.", 'version' => 2, 'accepted' => true],
    ], $board));

    fakeChaosDeskCommunity($overrides + [
        '*/public/community/boards/*' => fn (Request $request) => preg_match('#/boards/[^/]+$#', (string) parse_url($request->url(), PHP_URL_PATH)) === 1
            ? Http::response(['data' => $payload])
            : null,
    ]);
}

/**
 * A signed-in host user acting as the community member.
 */
function communityUser(): User
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    test()->actingAs($user);

    return $user;
}

/**
 * Every recorded request to the Community API, in order.
 *
 * @return Collection<int, Request>
 */
function communityRequests(): Collection
{
    return Http::recorded()
        ->map(fn (array $pair): Request => $pair[0])
        ->filter(fn (Request $request): bool => str_starts_with($request->url(), COMMUNITY_URL))
        ->values();
}

/**
 * A board as CommunityBoardResource returns it; with charter and member
 * state when fetched for a member.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function communityBoardPayload(array $overrides = [], bool $member = true): array
{
    $board = [
        'slug' => 'gurus',
        'name' => 'Gurus',
        'description' => 'Propose and discuss features with the team.',
        'allowed_kinds' => ['discussion', 'proposal', 'bug'],
        'charter_version' => 2,
    ];

    if ($member) {
        $board['charter'] = ['markdown' => "# Charter\n\nBe kind.", 'version' => 2, 'accepted' => false];
        $board['member'] = ['name' => 'Ada', 'is_blocked' => false];
    }

    // Not recursive: an override of allowed_kinds replaces the whole list.
    return array_replace($board, $overrides);
}

/**
 * A thread as CommunityThreadResource returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function communityThreadPayload(array $overrides = [], bool $withVote = true): array
{
    $thread = [
        'ulid' => '01JTHREADPROPOSAL000000000',
        'kind' => 'proposal',
        'title' => 'Export invoices to CSV',
        'body' => 'An export would save me an hour a month.',
        'status' => 'planned',
        'decline_reason' => null,
        'votes_count' => 7,
        'posts_count' => 2,
        'accepts_votes' => true,
        'is_pinned' => true,
        'is_locked' => false,
        'is_mine' => false,
        'author' => ['name' => 'Grace'],
        'created_at' => '2026-10-01T09:00:00+00:00',
        'last_activity_at' => '2026-10-02T08:30:00+00:00',
    ];

    if ($withVote) {
        $thread['has_voted'] = true;
    }

    return array_replace($thread, $overrides);
}

/**
 * A reply as CommunityPostResource returns it; a member post by default.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function communityPostPayload(array $overrides = []): array
{
    return array_replace([
        'ulid' => '01JPOSTMEMBER0000000000000',
        'body' => 'I would use this every week.',
        'is_official' => false,
        'is_agent' => false,
        'is_mine' => false,
        'author' => ['name' => 'Linus'],
        'created_at' => '2026-10-01T10:00:00+00:00',
    ], $overrides);
}

/**
 * A poll as CommunityPollResource returns it, loaded for a member.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function communityPollPayload(array $overrides = []): array
{
    return array_replace([
        'ulid' => '01JPOLL0000000000000000000',
        'question' => 'What should we build next?',
        'is_multiple_choice' => false,
        'is_open' => true,
        'is_closed' => false,
        'opens_at' => null,
        'closes_at' => '2026-10-09T12:00:00+00:00',
        'thread_ulid' => '01JTHREADPROPOSAL000000000',
        'options' => [
            ['ulid' => '01JOPTIONA0000000000000000', 'label' => 'CSV export'],
            ['ulid' => '01JOPTIONB0000000000000000', 'label' => 'Dark mode'],
        ],
        'my_options' => [],
        'created_at' => '2026-10-02T09:00:00+00:00',
    ], $overrides);
}

/**
 * The tally of a closed poll as CommunityPollResultsResource returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function communityPollResultsPayload(array $overrides = []): array
{
    return array_replace([
        'ulid' => '01JPOLL0000000000000000000',
        'question' => 'What should we build next?',
        'is_multiple_choice' => false,
        'closes_at' => '2026-10-09T12:00:00+00:00',
        'respondents_count' => 8,
        'options' => [
            ['ulid' => '01JOPTIONA0000000000000000', 'label' => 'CSV export', 'responses_count' => 6],
            ['ulid' => '01JOPTIONB0000000000000000', 'label' => 'Dark mode', 'responses_count' => 2],
        ],
    ], $overrides);
}

/**
 * A paginated resource collection as Laravel renders it.
 *
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function communityPage(array $items, ?int $total = null, int $lastPage = 1, int $perPage = 20): array
{
    return [
        'data' => $items,
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => [
            'current_page' => 1,
            'from' => $items === [] ? null : 1,
            'last_page' => $lastPage,
            'links' => [],
            'path' => 'https://chaosdesk.test/api/v1/public/community/boards/gurus/threads',
            'per_page' => $perPage,
            'to' => count($items),
            'total' => $total ?? count($items),
        ],
    ];
}

/**
 * The community member decoded from the X-Community-Member header of a request.
 *
 * @return array<string, mixed>|null
 */
function communityMemberSent(Request $request): ?array
{
    $header = $request->header('X-Community-Member')[0] ?? null;

    if (! is_string($header)) {
        return null;
    }

    $decoded = json_decode((string) base64_decode($header, true), true);

    return is_array($decoded) ? $decoded : null;
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
