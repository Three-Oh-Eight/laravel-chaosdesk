<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use ThreeOhEight\ChaosDesk\Contracts\ProvidesSupportContext;

/**
 * Resolves the stable identifier and display fields for the person raising a ticket.
 *
 * Authenticatable guarantees only an identifier, so name and email are read
 * defensively: a host application is free to model them differently.
 */
final class Identity
{
    public static function externalId(?Authenticatable $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user instanceof ProvidesSupportContext) {
            return $user->supportExternalId();
        }

        $key = $user->getAuthIdentifier();

        return $key === null ? null : (string) $key;
    }

    public static function email(?Authenticatable $user): ?string
    {
        return self::attribute($user, 'email');
    }

    public static function name(?Authenticatable $user): ?string
    {
        return self::attribute($user, 'name');
    }

    /**
     * Read an optional string attribute from a user of unknown shape.
     */
    private static function attribute(?Authenticatable $user, string $key): ?string
    {
        if ($user === null) {
            return null;
        }

        $value = data_get($user, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
