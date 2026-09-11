<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Http\Concerns;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;

/**
 * The HTTP plumbing shared by the ingest client and the agent client.
 *
 * Base url and timeouts come from the chaosdesk config, every write can carry
 * an Idempotency-Key, and a failed response or a connection failure surfaces
 * as a ChaosDeskException that tells the caller what went wrong.
 */
trait TalksToChaosDesk
{
    /**
     * The headers that identify this client: a site token or an agent bearer.
     *
     * @return array<string, string>
     *
     * @throws ChaosDeskException when the credential is not configured
     */
    abstract protected function authenticationHeaders(): array;

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function get(string $path, array $query = []): array
    {
        return $this->send(fn (PendingRequest $request): Response => $request->get($this->url($path, $query)));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function post(string $path, array $payload, array $query = [], array $headers = []): array
    {
        return $this->send(
            fn (PendingRequest $request): Response => $request->post($this->url($path, $query), $payload),
            $headers,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function patch(string $path, array $payload, array $headers = []): array
    {
        return $this->send(
            fn (PendingRequest $request): Response => $request->patch($this->url($path), $payload),
            $headers,
        );
    }

    /**
     * Run one request against ChaosDesk and turn its outcome into an array.
     *
     * @param  Closure(PendingRequest): Response  $call
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function send(Closure $call, array $headers = []): array
    {
        try {
            $response = $call($this->request($headers));
        } catch (ConnectionException $e) {
            throw ChaosDeskException::unavailable('ChaosDesk could not be reached: '.$this->connectionFailure($e));
        }

        return $this->handle($response);
    }

    /**
     * The transport error without the request url.
     *
     * Guzzle ends its connect errors in "for <url>", and that url can carry a
     * per-ticket access token in its query string. The original exception is
     * deliberately not chained either, so the token never reaches a log.
     */
    protected function connectionFailure(ConnectionException $e): string
    {
        $message = $e->getMessage();

        return preg_replace('/\s+for\s+\S+$/', '', $message) ?? $message;
    }

    /**
     * A fresh idempotency key for one logical write.
     *
     * The key is set on the pending request before the retry loop, so every
     * retry of the same call carries it and ChaosDesk replays the first
     * response instead of creating a duplicate.
     *
     * @return array<string, string>
     */
    protected function idempotencyHeaders(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function request(array $headers = []): PendingRequest
    {
        return Http::withHeaders($headers + $this->authenticationHeaders() + [
            'Accept' => 'application/json',
            'User-Agent' => 'laravel-chaosdesk/'.ChaosDesk::VERSION,
        ])
            ->timeout((int) config('chaosdesk.timeout', 10))
            ->retry((int) config('chaosdesk.retries', 2), 200, throw: false);
    }

    /**
     * Build an absolute API url. List values encode as `key[]=a&key[]=b`.
     *
     * @param  array<string, mixed>  $query
     */
    protected function url(string $path, array $query = []): string
    {
        $url = config('chaosdesk.url').'/'.ltrim($path, '/');

        return $query === [] ? $url : $url.'?'.$this->queryString($query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function queryString(array $query): string
    {
        $encoded = http_build_query($query);

        return preg_replace('/%5B\d+%5D=/', '%5B%5D=', $encoded) ?? $encoded;
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
