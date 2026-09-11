<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use ThreeOhEight\ChaosDesk\Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in(__DIR__);

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
