<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community;

use Closure;
use Illuminate\Support\Facades\Route;

/**
 * Where the community components link to. Your application owns the routes.
 *
 * By default a page resolves through the route name under
 * `chaosdesk.community.routes.{page}`; only the parameters the route
 * declares are passed (`board`, `thread`, `site`), so a route that fixes the
 * board in its path (`/guru/community`) needs none. Register a resolver with
 * resolveUsing() for anything a route name cannot express.
 *
 * A page without a url makes the board component show it inline instead.
 */
final class CommunityUrls
{
    public const BOARD = 'board';

    public const THREAD = 'thread';

    public const NEW_THREAD = 'new_thread';

    public const POLLS = 'polls';

    /**
     * @var (Closure(string, string, ?string, ?string): ?string)|null
     */
    private static ?Closure $resolver = null;

    /**
     * Resolve every community url with your own callback, or null to go back
     * to the configured route names.
     *
     * The callback receives the page (one of the constants), the board slug,
     * the thread ulid (only for THREAD) and the site name, and returns a url
     * or null to have the page shown inline.
     *
     * @param  (Closure(string, string, ?string, ?string): ?string)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function board(string $board, ?string $site = null): ?string
    {
        return self::resolve(self::BOARD, $board, null, $site);
    }

    public static function thread(string $board, string $thread, ?string $site = null): ?string
    {
        return self::resolve(self::THREAD, $board, $thread, $site);
    }

    public static function newThread(string $board, ?string $site = null): ?string
    {
        return self::resolve(self::NEW_THREAD, $board, null, $site);
    }

    public static function polls(string $board, ?string $site = null): ?string
    {
        return self::resolve(self::POLLS, $board, null, $site);
    }

    private static function resolve(string $page, string $board, ?string $thread, ?string $site): ?string
    {
        if (self::$resolver !== null) {
            $url = (self::$resolver)($page, $board, $thread, $site);

            return is_string($url) && $url !== '' ? $url : null;
        }

        $name = config("chaosdesk.community.routes.{$page}");

        if (! is_string($name) || $name === '') {
            return null;
        }

        $route = Route::getRoutes()->getByName($name);

        if ($route === null) {
            return null;
        }

        $available = ['board' => $board, 'thread' => $thread, 'site' => $site];
        $parameters = [];

        foreach ($route->parameterNames() as $parameter) {
            if (isset($available[$parameter])) {
                $parameters[$parameter] = $available[$parameter];
            }
        }

        return route($name, $parameters);
    }
}
