<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\ChaosDeskServiceProvider;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;

it('talks to the default site out of the box', function (): void {
    expect(app(ChaosDesk::class)->site())->toBe('default');
});

it('uses the named site token when asked for another site', function (): void {
    fakeChaosDesk();
    config(['chaosdesk.sites.gurus' => ['token' => 'gurus-token', 'agent_token' => null, 'site_id' => 2]]);

    $gurus = app(ChaosDesk::class)->forSite('gurus');

    expect($gurus)->toBeInstanceOf(ChaosDesk::class)
        ->and($gurus->site())->toBe('gurus')
        ->and(app(ChaosDesk::class)->site())->toBe('default');

    $gurus->config();

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Site-Token', 'gurus-token')
        && $request->url() === 'https://chaosdesk.test/api/v1/public/config');
});

it('throws a site-specific error when that site has no token', function (): void {
    config(['chaosdesk.sites.gurus' => ['token' => null]]);

    expect(fn () => app(ChaosDesk::class)->forSite('gurus')->config())
        ->toThrow(ChaosDeskException::class, 'No ChaosDesk site token configured for site [gurus]');
});

it('throws for a site that is not configured at all', function (): void {
    expect(fn () => app(ChaosDesk::class)->forSite('nowhere')->config())
        ->toThrow(ChaosDeskException::class, 'chaosdesk.sites.nowhere.token');
});

it('falls back to the single-site token for the default site', function (): void {
    fakeChaosDesk();
    config(['chaosdesk.sites.default.token' => null, 'chaosdesk.site_token' => 'legacy-token']);

    app(ChaosDesk::class)->config();

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Site-Token', 'legacy-token'));
});

it('prefers the named default token over the single-site token', function (): void {
    fakeChaosDesk();
    config(['chaosdesk.sites.default.token' => 'named-token', 'chaosdesk.site_token' => 'legacy-token']);

    app(ChaosDesk::class)->config();

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Site-Token', 'named-token'));
});

it('reports configuration per site', function (): void {
    config(['chaosdesk.sites.gurus' => ['token' => 'gurus-token']]);

    expect(app(ChaosDesk::class)->isConfigured())->toBeTrue()
        ->and(app(ChaosDesk::class)->forSite('gurus')->isConfigured())->toBeTrue()
        ->and(app(ChaosDesk::class)->forSite('nowhere')->isConfigured())->toBeFalse();
});

it('keeps the legacy missing-token message for the default site', function (): void {
    expect(ChaosDeskException::missingToken()->getMessage())
        ->toBe(ChaosDeskException::missingToken('default')->getMessage())
        ->toContain('CHAOSDESK_SITE_TOKEN');

    expect(ChaosDeskException::missingAgentToken('gurus')->getMessage())
        ->toContain('chaosdesk.sites.gurus.agent_token');
});

it('registers its migrations only when the switch is on', function (): void {
    $migrations = realpath(__DIR__.'/../database/migrations');
    $migrator = app('migrator');

    expect(array_map('realpath', $migrator->paths()))->toContain($migrations);

    // The migrator dedupes paths, so start from a clean list to observe a re-boot.
    $original = $migrator->paths();
    $reset = function (): void {
        $this->paths = [];
    };

    $reset->call($migrator);
    config(['chaosdesk.migrations' => false]);
    (new ChaosDeskServiceProvider(app()))->boot();

    expect($migrator->paths())->toBeEmpty();

    $reset->call($migrator);
    config(['chaosdesk.migrations' => true]);
    (new ChaosDeskServiceProvider(app()))->boot();

    expect(array_map('realpath', $migrator->paths()))->toBe([$migrations]);

    (function () use ($original): void {
        $this->paths = $original;
    })->call($migrator);
});
