<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array config()
 * @method static array createTicket(array $attributes, array $clientContext = [], ?\Illuminate\Contracts\Auth\Authenticatable $user = null)
 * @method static array ticket(string $ulid, string $accessToken)
 * @method static array reply(string $ulid, string $accessToken, string $body)
 * @method static array attach(string $ulid, string $accessToken, \Illuminate\Http\UploadedFile $file)
 * @method static array attachContents(string $ulid, string $accessToken, string $contents, string $filename)
 * @method static \ThreeOhEight\ChaosDesk\ChaosDesk forSite(string $name)
 * @method static string site()
 * @method static bool isConfigured()
 * @method static void routes()
 *
 * @see \ThreeOhEight\ChaosDesk\ChaosDesk
 */
class ChaosDesk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ThreeOhEight\ChaosDesk\ChaosDesk::class;
    }
}
