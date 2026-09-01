<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use ThreeOhEight\ChaosDesk\Context\ContextCollector;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Http\Controllers\TicketController;

/**
 * Server-to-server client for the ChaosDesk ingest API.
 *
 * Every call carries the site token, which is why this class only ever runs on
 * your server. Browsers and mobile apps reach ChaosDesk through your own
 * application, never directly.
 */
class ChaosDesk
{
    public const VERSION = '1.0.0';

    public function __construct(
        protected ContextCollector $context,
    ) {}

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

        return $this->post('public/tickets', $payload);
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
        return $this->post("public/tickets/{$ulid}/messages", ['body' => $body], ['access_token' => $accessToken]);
    }

    /**
     * Attach a file to a ticket.
     *
     * @return array<string, mixed>
     */
    public function attach(string $ulid, string $accessToken, UploadedFile $file): array
    {
        $response = $this->request()
            ->attach('file', $file->get(), $file->getClientOriginalName())
            ->post($this->url("public/tickets/{$ulid}/attachments", ['access_token' => $accessToken]));

        return $this->handle($response);
    }

    /**
     * Attach raw file contents, for example a screenshot decoded from a data URL.
     *
     * @return array<string, mixed>
     */
    public function attachContents(string $ulid, string $accessToken, string $contents, string $filename): array
    {
        $response = $this->request()
            ->attach('file', $contents, $filename)
            ->post($this->url("public/tickets/{$ulid}/attachments", ['access_token' => $accessToken]));

        return $this->handle($response);
    }

    /**
     * Whether the SDK is configured well enough to talk to ChaosDesk.
     */
    public function isConfigured(): bool
    {
        return (bool) config('chaosdesk.enabled') && is_string(config('chaosdesk.site_token'));
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
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function get(string $path, array $query = []): array
    {
        return $this->handle($this->request()->get($this->url($path, $query)));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function post(string $path, array $payload, array $query = []): array
    {
        return $this->handle($this->request()->post($this->url($path, $query), $payload));
    }

    protected function request(): PendingRequest
    {
        $token = config('chaosdesk.site_token');

        if (! is_string($token) || $token === '') {
            throw ChaosDeskException::missingToken();
        }

        return Http::withHeaders([
            'X-Site-Token' => $token,
            'Accept' => 'application/json',
            'User-Agent' => 'laravel-chaosdesk/'.self::VERSION,
        ])
            ->timeout((int) config('chaosdesk.timeout', 10))
            ->retry((int) config('chaosdesk.retries', 2), 200, throw: false);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function url(string $path, array $query = []): string
    {
        $url = config('chaosdesk.url').'/'.ltrim($path, '/');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    protected function handle(Response $response): array
    {
        if ($response->failed()) {
            throw ChaosDeskException::fromResponse($response);
        }

        return (array) $response->json();
    }
}
