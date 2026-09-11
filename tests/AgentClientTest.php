<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ThreeOhEight\ChaosDesk\Agent\AgentClient;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;

beforeEach(function (): void {
    config([
        'chaosdesk.sites.customers' => ['token' => 'customers-token', 'agent_token' => 'customers-agent-token', 'site_id' => 2],
        'chaosdesk.sites.gurus' => ['token' => 'gurus-token', 'agent_token' => 'gurus-agent-token', 'site_id' => 3],
    ]);
});

it('sends the bearer token of the named site', function (): void {
    fakeChaosDeskAgent();

    $agent = app(ChaosDesk::class)->agent('customers');

    expect($agent)->toBeInstanceOf(AgentClient::class)
        ->and($agent->site())->toBe('customers');

    $agent->tickets();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer customers-agent-token')
        && $request->hasHeader('Accept', 'application/json')
        && ! $request->hasHeader('X-Site-Token')
        && str_starts_with($request->url(), 'https://chaosdesk.test/api/v1/tickets?'));
});

it('defaults to the site of the client it was created from', function (): void {
    fakeChaosDeskAgent();

    app(ChaosDesk::class)->forSite('gurus')->agent()->tickets();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer gurus-agent-token'));
});

it('throws at construction when the site has no agent token', function (): void {
    expect(fn () => app(ChaosDesk::class)->agent('nowhere'))
        ->toThrow(ChaosDeskException::class, 'chaosdesk.sites.nowhere.agent_token');

    expect(fn () => app(ChaosDesk::class)->agent())
        ->toThrow(ChaosDeskException::class, 'CHAOSDESK_AGENT_TOKEN');

    expect(fn () => new AgentClient('customers', ''))
        ->toThrow(ChaosDeskException::class, 'chaosdesk.sites.customers.agent_token');
});

it('encodes filters, page and per_page as query parameters', function (): void {
    fakeChaosDeskAgent();

    app(ChaosDesk::class)->agent('customers')->tickets(
        ['site_id' => 2, 'status' => ['open', 'pending'], 'unassigned' => true, 'tag' => 'in-session'],
        page: 3,
        perPage: 50,
    );

    Http::assertSent(function (Request $request): bool {
        $query = urldecode((string) parse_url($request->url(), PHP_URL_QUERY));

        return $request->method() === 'GET'
            && str_contains($query, 'status[]=open&status[]=pending')
            && str_contains($query, 'site_id=2')
            && str_contains($query, 'unassigned=1')
            && str_contains($query, 'tag=in-session')
            && str_contains($query, 'page=3')
            && str_contains($query, 'per_page=50');
    });
});

it('returns the ticket paginator untouched', function (): void {
    fakeChaosDeskAgent();

    $page = app(ChaosDesk::class)->agent('customers')->tickets();

    expect($page)->toHaveKeys(['data', 'links', 'meta'])
        ->and($page['data'])->toHaveCount(2)
        ->and($page['data'][0]['tags'])->toBe(['in-session', 'complaint'])
        ->and($page['data'][0]['author']['external_id'])->toBe('user:7')
        ->and($page['data'][0]['assignee']['name'])->toBe('Styn')
        ->and($page['data'][1])->not->toHaveKey('assignee')
        ->and($page['meta']['total'])->toBe(2);
});

it('unwraps a single ticket with its messages', function (): void {
    fakeChaosDeskAgent();

    $ticket = app(ChaosDesk::class)->agent('customers')->ticket('01JABCDEFGHIJKLMNOPQRSTUVW');

    expect($ticket['ulid'])->toBe('01JABCDEFGHIJKLMNOPQRSTUVW')
        ->and($ticket)->not->toHaveKey('data')
        ->and($ticket['messages'])->toHaveCount(2)
        ->and($ticket['messages'][0]['is_agent'])->toBeFalse()
        ->and($ticket['messages'][1]['is_agent'])->toBeTrue()
        ->and($ticket['messages'][1]['agent_name'])->toBe('Styn');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://chaosdesk.test/api/v1/tickets/01JABCDEFGHIJKLMNOPQRSTUVW');
});

it('patches a ticket and returns the updated one', function (): void {
    fakeChaosDeskAgent([
        '*/api/v1/tickets/*' => Http::response(['data' => agentTicketPayload(['status' => 'resolved'])]),
    ]);

    $ticket = app(ChaosDesk::class)->agent('customers')->update('01JABCDEFGHIJKLMNOPQRSTUVW', [
        'status' => 'resolved',
        'assigned_to' => 3,
        'tags' => ['in-session'],
    ]);

    expect($ticket['status'])->toBe('resolved');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && $request->url() === 'https://chaosdesk.test/api/v1/tickets/01JABCDEFGHIJKLMNOPQRSTUVW'
        && $request->data() === ['status' => 'resolved', 'assigned_to' => 3, 'tags' => ['in-session']]);
});

it('replies with an idempotency key and the agent name', function (): void {
    fakeChaosDeskAgent();

    $message = app(ChaosDesk::class)->agent('customers')
        ->reply('01JABCDEFGHIJKLMNOPQRSTUVW', 'We are looking into it.', agentName: 'Styn');

    expect($message['is_agent'])->toBeTrue()
        ->and($message['agent_name'])->toBe('Styn')
        ->and(idempotencyKeysSent()->first())->not->toBeEmpty();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://chaosdesk.test/api/v1/tickets/01JABCDEFGHIJKLMNOPQRSTUVW/messages'
        && $request->data() === ['body' => 'We are looking into it.', 'is_internal' => false, 'agent_name' => 'Styn']);
});

it('marks an internal reply and omits the agent name when none is given', function (): void {
    fakeChaosDeskAgent();

    app(ChaosDesk::class)->agent('customers')->reply('01JABCDEFGHIJKLMNOPQRSTUVW', 'Internal.', internal: true);

    Http::assertSent(fn (Request $request): bool => $request->data() === ['body' => 'Internal.', 'is_internal' => true]);
});

it('adds a note with an idempotency key', function (): void {
    fakeChaosDeskAgent();

    $note = app(ChaosDesk::class)->agent('customers')
        ->note('01JABCDEFGHIJKLMNOPQRSTUVW', 'Checked the billing log.', 'Christoph');

    expect($note['is_internal'])->toBeTrue()
        ->and($note['body'])->toBe('Checked the billing log.')
        ->and(idempotencyKeysSent()->first())->not->toBeEmpty();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://chaosdesk.test/api/v1/tickets/01JABCDEFGHIJKLMNOPQRSTUVW/notes'
        && $request->data() === ['body' => 'Checked the billing log.', 'agent_name' => 'Christoph']);
});

it('sends a fresh idempotency key per write', function (): void {
    fakeChaosDeskAgent();

    $agent = app(ChaosDesk::class)->agent('customers');
    $agent->reply('01JABCDEFGHIJKLMNOPQRSTUVW', 'One.');
    $agent->note('01JABCDEFGHIJKLMNOPQRSTUVW', 'Two.');

    expect(idempotencyKeysSent()->unique())->toHaveCount(2);
});

it('anonymises an author by external id and site id', function (): void {
    fakeChaosDeskAgent();

    $result = app(ChaosDesk::class)->agent('customers')->anonymiseAuthor('user:7', 2);

    expect($result['author']['name'])->toBe('Anonymised')
        ->and($result['tickets_scrubbed'])->toBe(1);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://chaosdesk.test/api/v1/authors/anonymise'
        && $request->data() === ['site_id' => 2, 'external_id' => 'user:7']);
});

it('lists agents, categories, priorities and sites unwrapped', function (): void {
    fakeChaosDeskAgent();

    $agent = app(ChaosDesk::class)->agent('customers');

    expect($agent->agents(2))->toHaveCount(2)
        ->and($agent->agents(2)[0]['role'])->toBe('owner')
        ->and($agent->categories(2)[0]['slug'])->toBe('session-issue')
        ->and($agent->priorities(2)[0]['is_default'])->toBeTrue()
        ->and($agent->sites())->toHaveCount(2)
        ->and($agent->sites()[1]['slug'])->toBe('gurus');

    foreach ([
        'https://chaosdesk.test/api/v1/sites/2/agents',
        'https://chaosdesk.test/api/v1/sites/2/categories',
        'https://chaosdesk.test/api/v1/sites/2/priorities',
        'https://chaosdesk.test/api/v1/sites',
    ] as $url) {
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->url() === $url);
    }
});

it('turns a 401 into an unauthorised exception', function (): void {
    Http::fake(['*' => Http::response(['error' => 'Unauthenticated.'], 401)]);

    try {
        app(ChaosDesk::class)->agent('customers')->tickets();
        $this->fail('Expected a ChaosDeskException.');
    } catch (ChaosDeskException $e) {
        expect($e->isUnauthorised())->toBeTrue()
            ->and($e->status)->toBe(401)
            ->and($e->getMessage())->toBe('Unauthenticated.')
            ->and($e->isUnavailable())->toBeFalse();
    }
});

it('turns a 403 into an unauthorised exception too', function (): void {
    Http::fake(['*' => Http::response(['message' => 'You are not authorized to view categories for this site.'], 403)]);

    expect(fn () => app(ChaosDesk::class)->agent('customers')->categories(9))
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isUnauthorised())->toBeTrue()->and($e->status)->toBe(403);
        });
});

it('turns a 404 into a not-found exception', function (): void {
    Http::fake(['*' => Http::response(['message' => 'No query results.'], 404)]);

    expect(fn () => app(ChaosDesk::class)->agent('customers')->ticket('01JNOPE'))
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isNotFound())->toBeTrue()
                ->and($e->status)->toBe(404)
                ->and($e->getMessage())->toBe('No query results.');
        });
});

it('turns a 422 into a validation exception carrying the errors', function (): void {
    Http::fake(['*' => Http::response([
        'message' => 'The given data was invalid.',
        'errors' => ['status' => ['The selected status is invalid.']],
    ], 422)]);

    try {
        app(ChaosDesk::class)->agent('customers')->update('01JABCDEFGHIJKLMNOPQRSTUVW', ['status' => 'nope']);
        $this->fail('Expected a ChaosDeskException.');
    } catch (ChaosDeskException $e) {
        expect($e->isValidationError())->toBeTrue()
            ->and($e->errors)->toBe(['status' => ['The selected status is invalid.']])
            ->and($e->isUnauthorised())->toBeFalse();
    }
});

it('turns a 503 into an unavailable exception', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Service Unavailable'], 503)]);

    expect(fn () => app(ChaosDesk::class)->agent('customers')->sites())
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isUnavailable())->toBeTrue()
                ->and($e->status)->toBe(503)
                ->and($e->isValidationError())->toBeFalse();
        });
});

it('turns a connection failure into an unavailable exception', function (): void {
    Http::fake(['*' => Http::failedConnection('Connection refused')]);

    expect(fn () => app(ChaosDesk::class)->agent('customers')->sites())
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isUnavailable())->toBeTrue()
                ->and($e->status)->toBeNull()
                ->and($e->getMessage())->toContain('Connection refused');
        });
});

it('strips the request url from a connection failure so the access token never leaks', function (): void {
    Http::fake(['*' => Http::failedConnection(
        'cURL error 6: Could not resolve host: x for https://x/api/v1/public/tickets/abc?access_token=secret'
    )]);

    expect(fn () => app(ChaosDesk::class)->ticket('abc', 'secret'))
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isUnavailable())->toBeTrue()
                ->and($e->getMessage())->toBe('ChaosDesk could not be reached: cURL error 6: Could not resolve host: x')
                ->and($e->getMessage())->not->toContain('access_token')
                ->and($e->getMessage())->not->toContain('secret')
                ->and($e->getPrevious())->toBeNull();
        });
});

it('maps the same statuses for the ingest client', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Ticket not found.'], 404)]);

    expect(fn () => app(ChaosDesk::class)->ticket('01JNOPE', 'token'))
        ->toThrow(function (ChaosDeskException $e): void {
            expect($e->isNotFound())->toBeTrue()->and($e->isUnavailable())->toBeFalse();
        });
});
