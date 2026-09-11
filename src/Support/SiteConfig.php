<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Support;

/**
 * Reads the per-site credentials from chaosdesk.sites.
 *
 * The "default" site falls back to the single-site "site_token" and
 * "agent_token" keys, so an install that predates named sites keeps working
 * without a config change.
 */
final class SiteConfig
{
    public const DEFAULT = 'default';

    /**
     * The ingest token for a site, or null when none is configured.
     */
    public static function token(string $site): ?string
    {
        $token = self::string(config("chaosdesk.sites.{$site}.token"));

        if ($token === null && $site === self::DEFAULT) {
            return self::string(config('chaosdesk.site_token'));
        }

        return $token;
    }

    /**
     * The team API token used to act as an agent on a site, or null.
     */
    public static function agentToken(string $site): ?string
    {
        $token = self::string(config("chaosdesk.sites.{$site}.agent_token"));

        if ($token === null && $site === self::DEFAULT) {
            return self::string(config('chaosdesk.agent_token'));
        }

        return $token;
    }

    /**
     * The numeric id ChaosDesk assigned to the site, or null when unknown.
     */
    public static function siteId(string $site): ?int
    {
        $id = config("chaosdesk.sites.{$site}.site_id");

        if (is_int($id)) {
            return $id;
        }

        return is_string($id) && ctype_digit($id) ? (int) $id : null;
    }

    /**
     * The configured site names, "default" first when it has a token.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $names = array_map('strval', array_keys((array) config('chaosdesk.sites', [])));

        if (! in_array(self::DEFAULT, $names, true) && self::token(self::DEFAULT) !== null) {
            array_unshift($names, self::DEFAULT);
        }

        return array_values($names);
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
