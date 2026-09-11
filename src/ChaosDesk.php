<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use ThreeOhEight\ChaosDesk\Agent\AgentClient;
use ThreeOhEight\ChaosDesk\Context\ContextCollector;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Http\Concerns\TalksToChaosDesk;
use ThreeOhEight\ChaosDesk\Http\Controllers\TicketController;
use ThreeOhEight\ChaosDesk\Support\SiteConfig;

/**
 * Server-to-server client for the ChaosDesk ingest API.
 *
 * Every call carries the site token, which is why this class only ever runs on
 * your server. Browsers and mobile apps reach ChaosDesk through your own
 * application, never directly.
 */
class ChaosDesk
{
    use TalksToChaosDesk;

    public const VERSION = '1.1.0';

    public function __construct(
        protected ContextCollector $context,
        protected string $site = SiteConfig::DEFAULT,
    ) {}

    /**
     * A client for another configured site, sharing the same context collector.
     *
     * A subclass that changes the constructor signature overrides this too.
     */
    public function forSite(string $name): static
    {
        /** @phpstan-ignore new.static */
        return new static($this->context, $name);
    }

    /**
     * The name of the site this client talks to.
     */
    public function site(): string
    {
        return $this->site;
    }

    /**
     * An agent client acting on a site with its team API token.
     *
     * Defaults to the site this client talks to. Throws when that site has no
     * agent token configured, so a misconfigured host fails before any call.
     */
    public function agent(?string $site = null): AgentClient
    {
        $site ??= $this->site;
        $token = SiteConfig::agentToken($site);

        if ($token === null) {
            throw ChaosDeskException::missingAgentToken($site);
        }

        return new AgentClient($site, $token);
    }

    /**
     * Fetch the site configuration: categories, priorities and custom fields.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->get('public/config');
    }

    /**
     * Create a ticket, attaching the collected diagnostic context.
     *
     * @param  array<string, mixed>  $attributes  email, name, subject, message, category_id, priority_id, custom_fields
     * @param  array<string, mixed>  $clientContext  context reported by the browser or a mobile client
     * @return array<string, mixed> the created ticket plus its access_token
     */
    public function createTicket(
        array $attributes,
        array $clientContext = [],
        ?Authenticatable $user = null,
    ): array {
        $payload = $attributes + [
            'context' => $this->context->collect($clientContext, $user),
        ];

        return $this->post('public/tickets', $payload, headers: $this->idempotencyHeaders());
    }

    /**
     * Fetch a ticket the caller holds an access token for.
     *
     * @return array<string, mixed>
     */
    public function ticket(string $ulid, string $accessToken): array
    {
        return $this->get("public/tickets/{$ulid}", ['access_token' => $accessToken]);
    }

    /**
     * Add a customer reply to a ticket.
     *
     * @return array<string, mixed>
     */
    public function reply(string $ulid, string $accessToken, string $body): array
    {
        return $this->post(
            "public/tickets/{$ulid}/messages",
            ['body' => $body],
            ['access_token' => $accessToken],
            $this->idempotencyHeaders(),
        );
    }

    /**
     * Attach a file to a ticket.
     *
     * @return array<string, mixed>
     */
    public function attach(string $ulid, string $accessToken, UploadedFile $file): array
    {
        return $this->attachContents($ulid, $accessToken, (string) $file->get(), $file->getClientOriginalName());
    }

    /**
     * Attach raw file contents, for example a screenshot decoded from a data URL.
     *
     * @return array<string, mixed>
     */
    public function attachContents(string $ulid, string $accessToken, string $contents, string $filename): array
    {
        return $this->send(fn (PendingRequest $request): Response => $request
            ->attach('file', $contents, $filename)
            ->post($this->url("public/tickets/{$ulid}/attachments", ['access_token' => $accessToken])));
    }

    /**
     * Whether the SDK is configured well enough to talk to ChaosDesk.
     */
    public function isConfigured(): bool
    {
        return (bool) config('chaosdesk.enabled') && SiteConfig::token($this->site) !== null;
    }

    /**
     * Register the endpoints your mobile apps post to.
     *
     * Wrap this in your own authentication middleware: ChaosDesk trusts the
     * identity your application has already established.
     */
    public function routes(): void
    {
        Route::prefix((string) config('chaosdesk.routes.prefix'))
            ->name((string) config('chaosdesk.routes.name'))
            ->group(function (): void {
                Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
                Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
                Route::get('tickets/{ulid}', [TicketController::class, 'show'])->name('tickets.show');
                Route::post('tickets/{ulid}/messages', [TicketController::class, 'reply'])->name('tickets.reply');
                Route::post('tickets/{ulid}/attachments', [TicketController::class, 'attach'])->name('tickets.attach');
            });
    }

    /**
     * The ingest API authenticates with the site token.
     *
     * @return array<string, string>
     */
    protected function authenticationHeaders(): array
    {
        $token = SiteConfig::token($this->site);

        if ($token === null) {
            throw ChaosDeskException::missingToken($this->site);
        }

        return ['X-Site-Token' => $token];
    }
}
