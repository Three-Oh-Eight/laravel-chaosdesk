<?php

declare(strict_types=1);

use ThreeOhEight\ChaosDesk\Support\SiteConfig;

beforeEach(function (): void {
    config([
        'chaosdesk.site_token' => 'legacy-token',
        'chaosdesk.sites' => [
            'default' => ['token' => null, 'agent_token' => null, 'site_id' => null],
            'customers' => ['token' => 'customers-token', 'agent_token' => 'customers-agent', 'site_id' => 7],
            'gurus' => ['token' => 'gurus-token', 'agent_token' => '', 'site_id' => '12'],
        ],
    ]);
});

it('reads the token of a named site', function (): void {
    expect(SiteConfig::token('customers'))->toBe('customers-token')
        ->and(SiteConfig::token('gurus'))->toBe('gurus-token')
        ->and(SiteConfig::token('nowhere'))->toBeNull();
});

it('falls back to site_token for the default site only', function (): void {
    expect(SiteConfig::token('default'))->toBe('legacy-token');

    config(['chaosdesk.sites.default.token' => 'named-token']);

    expect(SiteConfig::token('default'))->toBe('named-token');

    config(['chaosdesk.sites.default.token' => '', 'chaosdesk.site_token' => '']);

    expect(SiteConfig::token('default'))->toBeNull();
});

it('reads the agent token of a named site without falling back', function (): void {
    config(['chaosdesk.agent_token' => 'legacy-agent']);

    expect(SiteConfig::agentToken('customers'))->toBe('customers-agent')
        ->and(SiteConfig::agentToken('gurus'))->toBeNull()
        ->and(SiteConfig::agentToken('nowhere'))->toBeNull();
});

it('falls back to agent_token for the default site only', function (): void {
    expect(SiteConfig::agentToken('default'))->toBeNull();

    config(['chaosdesk.agent_token' => 'legacy-agent']);

    expect(SiteConfig::agentToken('default'))->toBe('legacy-agent');

    config(['chaosdesk.sites.default.agent_token' => 'named-agent']);

    expect(SiteConfig::agentToken('default'))->toBe('named-agent');

    config(['chaosdesk.sites.default.agent_token' => '', 'chaosdesk.agent_token' => '']);

    expect(SiteConfig::agentToken('default'))->toBeNull();
});

it('serves the agent client from a published 1.0 config that only knows agent_token', function (): void {
    config(['chaosdesk.sites' => null, 'chaosdesk.agent_token' => 'legacy-agent']);

    expect(SiteConfig::agentToken('default'))->toBe('legacy-agent');
});

it('reads the numeric site id', function (): void {
    expect(SiteConfig::siteId('customers'))->toBe(7)
        ->and(SiteConfig::siteId('gurus'))->toBe(12)
        ->and(SiteConfig::siteId('default'))->toBeNull()
        ->and(SiteConfig::siteId('nowhere'))->toBeNull();
});

it('lists the configured site names', function (): void {
    expect(SiteConfig::names())->toBe(['default', 'customers', 'gurus']);
});

it('adds default to the names when only the legacy token is set', function (): void {
    config(['chaosdesk.sites' => ['customers' => ['token' => 'customers-token']]]);

    expect(SiteConfig::names())->toBe(['default', 'customers']);

    config(['chaosdesk.site_token' => null]);

    expect(SiteConfig::names())->toBe(['customers']);
});
