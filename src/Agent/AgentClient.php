<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Agent;

use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Http\Concerns\TalksToChaosDesk;

/**
 * Server-to-server client for the ChaosDesk agent API.
 *
 * Acts on a site with a team API token: lists and updates tickets, replies
 * as an agent, leaves internal notes and anonymises authors. Obtain one via
 * ChaosDesk::agent(); the bearer token never leaves your server.
 *
 * Endpoints that wrap their payload in `data` are unwrapped, except
 * tickets(), which returns the paginator (`data`, `links`, `meta`) untouched.
 */
final class AgentClient
{
    use TalksToChaosDesk;

    public function __construct(
        private readonly string $site,
        private readonly string $token,
    ) {
        if ($this->token === '') {
            throw ChaosDeskException::missingAgentToken($this->site);
        }
    }

    /**
     * The configured site name this client acts on.
     */
    public function site(): string
    {
        return $this->site;
    }

    /**
     * List tickets. Filters pass through as query parameters; list values
     * encode as `status[]=open&status[]=pending`.
     *
     * @param  array<string, mixed>  $filters  site_id, status[], assigned_to, unassigned, external_id, email, tag, q, created_from, created_to, sort, direction, include_test
     * @return array<string, mixed> the paginator: data, links, meta
     */
    public function tickets(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        return $this->get('tickets', array_merge($filters, ['page' => $page, 'per_page' => $perPage]));
    }

    /**
     * Fetch a ticket with its messages (internal notes included) and attachments.
     *
     * @return array<string, mixed>
     */
    public function ticket(string $ulid): array
    {
        return $this->data($this->get("tickets/{$ulid}"));
    }

    /**
     * Change a ticket: status, category_id, priority_id, assigned_to, tags.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed> the updated ticket
     */
    public function update(string $ulid, array $attributes): array
    {
        return $this->data($this->patch("tickets/{$ulid}", $attributes));
    }

    /**
     * Reply to a ticket as an agent, optionally as an internal message.
     *
     * @return array<string, mixed> the created message
     */
    public function reply(string $ulid, string $body, bool $internal = false, ?string $agentName = null): array
    {
        $response = $this->post(
            "tickets/{$ulid}/messages",
            $this->withAgentName(['body' => $body, 'is_internal' => $internal], $agentName),
            headers: $this->idempotencyHeaders(),
        );

        return $this->unwrap($response, 'reply');
    }

    /**
     * Leave an internal note on a ticket; the requester never sees it.
     *
     * @return array<string, mixed> the created note
     */
    public function note(string $ulid, string $body, ?string $agentName = null): array
    {
        $response = $this->post(
            "tickets/{$ulid}/notes",
            $this->withAgentName(['body' => $body], $agentName),
            headers: $this->idempotencyHeaders(),
        );

        return $this->unwrap($response, 'note');
    }

    /**
     * Blank an author's name and email on a site, for example after an
     * account deletion. Idempotent: repeating the call answers 200 again.
     *
     * @return array<string, mixed> message, author, tickets_scrubbed
     */
    public function anonymiseAuthor(string $externalId, int $siteId): array
    {
        return $this->post('authors/anonymise', ['site_id' => $siteId, 'external_id' => $externalId]);
    }

    /**
     * The people a ticket on this site can be assigned to.
     *
     * @return list<array<string, mixed>>
     */
    public function agents(int|string $site): array
    {
        return $this->list($this->get("sites/{$site}/agents"));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(int $siteId): array
    {
        return $this->list($this->get("sites/{$siteId}/categories"));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function priorities(int $siteId): array
    {
        return $this->list($this->get("sites/{$siteId}/priorities"));
    }

    /**
     * The sites this token may reach.
     *
     * @return list<array<string, mixed>>
     */
    public function sites(): array
    {
        return $this->list($this->get('sites'));
    }

    /**
     * The agent API authenticates with a team bearer token.
     *
     * @return array<string, string>
     */
    protected function authenticationHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withAgentName(array $payload, ?string $agentName): array
    {
        return $agentName === null ? $payload : $payload + ['agent_name' => $agentName];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function data(array $response): array
    {
        return $this->unwrap($response, 'data');
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function list(array $response): array
    {
        $data = $response['data'] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function unwrap(array $response, string $key): array
    {
        $inner = $response[$key] ?? null;

        return is_array($inner) ? $inner : $response;
    }
}
