<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Webhooks;

/**
 * Verifies the X-ChaosDesk-Signature header on an incoming webhook.
 *
 * ChaosDesk signs the raw request body with HMAC-SHA256 under the channel
 * secret and sends `sha256=<hex>`. Verify against the raw body, never the
 * decoded and re-encoded payload, or the digest will not match.
 */
final class Signature
{
    private const PREFIX = 'sha256=';

    /**
     * Whether the header matches the body under the secret, in constant time.
     *
     * A missing or malformed header, or an empty secret, never verifies.
     */
    public static function verify(string $rawBody, ?string $header, string $secret): bool
    {
        if ($secret === '' || $header === null) {
            return false;
        }

        if (preg_match('/^sha256=([0-9a-f]{64})$/i', trim($header), $matches) !== 1) {
            return false;
        }

        return hash_equals(self::digest($rawBody, $secret), strtolower($matches[1]));
    }

    /**
     * The header value ChaosDesk would send for this body, for tests and hosts.
     */
    public static function sign(string $rawBody, string $secret): string
    {
        return self::PREFIX.self::digest($rawBody, $secret);
    }

    private static function digest(string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $rawBody, $secret);
    }
}
