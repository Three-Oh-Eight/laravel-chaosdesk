<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Verifies the X-ChaosDesk-Signature header on an incoming webhook.
 *
 * ChaosDesk signs `<timestamp>.<raw body>` with HMAC-SHA256 under the channel
 * secret, sends the unix timestamp in X-ChaosDesk-Timestamp and the digest
 * as `sha256=<hex>`. A delivery whose timestamp lies further than the
 * tolerance (`chaosdesk.webhooks.tolerance`, 300 seconds by default) from now
 * is refused, so a captured delivery cannot be replayed later. Verify against
 * the raw body, never the decoded and re-encoded payload, or the digest will
 * not match.
 *
 * Deprecated, removed in the next release: a delivery without the timestamp
 * header still verifies against the body alone, for senders that do not send
 * one yet. Set `chaosdesk.webhooks.require_timestamp` to refuse those as soon
 * as your ChaosDesk sends the timestamp.
 */
final class Signature
{
    /**
     * Seconds a signed timestamp may differ from now, either way.
     */
    public const DEFAULT_TOLERANCE = 300;

    private const PREFIX = 'sha256=';

    /**
     * Whether the header matches the body (and timestamp) under the secret,
     * in constant time.
     *
     * A missing or malformed header, an empty secret, a malformed timestamp
     * or one outside the tolerance never verifies. Without a timestamp the
     * legacy body-only signature is checked, unless timestamps are required.
     */
    public static function verify(
        string $rawBody,
        ?string $header,
        string $secret,
        ?string $timestamp = null,
        ?int $tolerance = null,
    ): bool {
        if ($secret === '' || $header === null) {
            return false;
        }

        if (preg_match('/^sha256=([0-9a-f]{64})$/i', trim($header), $matches) !== 1) {
            return false;
        }

        $given = strtolower($matches[1]);
        $timestamp = trim($timestamp ?? '');

        if ($timestamp === '') {
            return self::verifyLegacy($rawBody, $given, $secret);
        }

        if (preg_match('/^\d{1,12}$/', $timestamp) !== 1) {
            return false;
        }

        if (abs(Date::now()->getTimestamp() - (int) $timestamp) > ($tolerance ?? self::tolerance())) {
            return false;
        }

        return hash_equals(self::digest($timestamp.'.'.$rawBody, $secret), $given);
    }

    /**
     * Verify a request: its raw body, the signature header and the timestamp header.
     */
    public static function verifyRequest(Request $request, string $secret, ?int $tolerance = null): bool
    {
        return self::verify(
            $request->getContent(),
            $request->header(WebhookEvent::SIGNATURE_HEADER),
            $secret,
            $request->header(WebhookEvent::TIMESTAMP_HEADER),
            $tolerance,
        );
    }

    /**
     * The signature header value ChaosDesk would send, for tests and hosts.
     *
     * With a timestamp the digest covers `<timestamp>.<body>`; without one it
     * is the deprecated body-only digest.
     */
    public static function sign(string $rawBody, string $secret, ?int $timestamp = null): string
    {
        $signed = $timestamp === null ? $rawBody : $timestamp.'.'.$rawBody;

        return self::PREFIX.self::digest($signed, $secret);
    }

    /**
     * Both headers ChaosDesk would send for this body, signed now unless a
     * timestamp is given.
     *
     * @return array<string, string> keyed by WebhookEvent::TIMESTAMP_HEADER and SIGNATURE_HEADER
     */
    public static function headers(string $rawBody, string $secret, ?int $timestamp = null): array
    {
        $timestamp ??= Date::now()->getTimestamp();

        return [
            WebhookEvent::TIMESTAMP_HEADER => (string) $timestamp,
            WebhookEvent::SIGNATURE_HEADER => self::sign($rawBody, $secret, $timestamp),
        ];
    }

    /**
     * The body-only check for senders without a timestamp.
     *
     * A body shaped like a timestamped signing input (`<digits>.`) is refused:
     * otherwise a captured timestamped delivery could be replayed by moving
     * its timestamp into the body and dropping the timestamp header.
     */
    private static function verifyLegacy(string $rawBody, string $given, string $secret): bool
    {
        if ((bool) config('chaosdesk.webhooks.require_timestamp', false)) {
            return false;
        }

        if (preg_match('/^\d+\./', $rawBody) === 1) {
            return false;
        }

        return hash_equals(self::digest($rawBody, $secret), $given);
    }

    private static function tolerance(): int
    {
        $tolerance = config('chaosdesk.webhooks.tolerance', self::DEFAULT_TOLERANCE);

        return is_numeric($tolerance) ? max(0, (int) $tolerance) : self::DEFAULT_TOLERANCE;
    }

    private static function digest(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}
