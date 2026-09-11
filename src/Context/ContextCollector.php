<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Context;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Contracts\ProvidesSupportContext;
use ThreeOhEight\ChaosDesk\Support\Identity;

/**
 * Builds the diagnostic context that travels with a ticket.
 *
 * Server-derived facts (app version, runtime, the authenticated user) are
 * collected here. Client-supplied facts (page URL, viewport, console errors)
 * arrive from the browser and are merged in without being trusted for identity.
 */
class ContextCollector
{
    public function __construct(
        protected Application $app,
        protected Request $request,
    ) {}

    /**
     * Collect the full context for the current request.
     *
     * @param  array<string, mixed>  $client  context reported by the browser or a mobile client
     * @return array<string, mixed>
     */
    public function collect(array $client = [], ?Authenticatable $user = null): array
    {
        $enabled = config('chaosdesk.context');

        $context = array_filter([
            'source' => $client['source'] ?? 'web',
            'sdk' => $this->sdk(),
            'app' => ($enabled['app'] ?? true) ? $this->appContext((array) ($client['app'] ?? [])) : null,
            'runtime' => ($enabled['runtime'] ?? true) ? $this->runtime() : null,
            'page' => ($enabled['page'] ?? true) ? $this->page($client['page'] ?? []) : null,
            'device' => Arr::only((array) ($client['device'] ?? []), ['platform', 'os_version', 'model', 'locale', 'timezone']) ?: null,
            'user' => ($enabled['user'] ?? true) ? $this->user($user) : null,
            'console' => ($enabled['console'] ?? true) ? $this->console($client['console'] ?? []) : null,
            'extra' => $client['extra'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        return $context;
    }

    /**
     * @return array<string, string>
     */
    protected function sdk(): array
    {
        return ['name' => 'laravel-chaosdesk', 'version' => ChaosDesk::VERSION];
    }

    /**
     * The application as the server knows it, with the client's own version
     * and build winning: a mobile app reports the binary the user actually
     * runs, which the server cannot know.
     *
     * @param  array<string, mixed>  $client
     * @return array<string, string>
     */
    protected function appContext(array $client = []): array
    {
        $reported = array_filter(
            Arr::only($client, ['version', 'build']),
            fn (mixed $value): bool => is_string($value) && $value !== '',
        );

        return array_filter([
            'name' => (string) config('app.name'),
            'version' => $reported['version'] ?? $this->appVersion(),
            'build' => $reported['build'] ?? null,
            'environment' => $this->app->environment(),
        ]);
    }

    /**
     * Resolve the application version from config, then git, then nothing.
     */
    protected function appVersion(): ?string
    {
        $configured = config('chaosdesk.context.app_version');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function runtime(): array
    {
        return [
            'php' => PHP_VERSION,
            'framework' => $this->app->version(),
        ];
    }

    /**
     * Merge the browser's view of the page with what the request can tell us.
     *
     * @param  array<string, mixed>  $client
     * @return array<string, string>
     */
    protected function page(array $client): array
    {
        return array_filter([
            'url' => $client['url'] ?? $this->request->fullUrl(),
            'referrer' => $client['referrer'] ?? $this->request->header('referer'),
            'viewport' => $client['viewport'] ?? null,
            'user_agent' => $this->request->userAgent(),
            'locale' => $client['locale'] ?? $this->app->getLocale(),
        ], fn (mixed $value): bool => is_string($value) && $value !== '');
    }

    /**
     * @return array<string, string>|null
     */
    protected function user(?Authenticatable $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $context = array_filter([
            'name' => Identity::name($user),
            'email' => Identity::email($user),
        ], fn (mixed $value): bool => is_string($value) && $value !== '');

        if ($user instanceof ProvidesSupportContext) {
            $context['external_id'] = $user->supportExternalId();
            $context += array_filter(
                Arr::only($user->supportContext(), ['plan', 'signed_up_at']),
                fn (mixed $value): bool => is_string($value) && $value !== '',
            );
        } elseif ($user->getAuthIdentifier() !== null) {
            $context['external_id'] = (string) $user->getAuthIdentifier();
        }

        return $context ?: null;
    }

    /**
     * @return list<array{level: string, message: string, at?: string}>
     */
    protected function console(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        return collect($entries)
            ->filter(fn (mixed $entry): bool => is_array($entry) && is_string($entry['message'] ?? null))
            ->map(fn (array $entry): array => array_filter([
                'level' => in_array($entry['level'] ?? null, ['debug', 'info', 'warning', 'error'], true)
                    ? $entry['level']
                    : 'error',
                'message' => mb_substr($entry['message'], 0, 2000),
                'at' => is_string($entry['at'] ?? null) ? mb_substr($entry['at'], 0, 64) : null,
            ], fn (mixed $value): bool => $value !== null))
            ->take(-50)
            ->values()
            ->all();
    }
}
