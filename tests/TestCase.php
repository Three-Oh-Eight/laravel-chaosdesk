<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ThreeOhEight\ChaosDesk\ChaosDeskServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            ChaosDeskServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('chaosdesk.url', 'https://chaosdesk.test/api/v1');
        $app['config']->set('chaosdesk.site_token', 'test-site-token');
        $app['config']->set('chaosdesk.retries', 1);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // A test that forgets to fake a call should fail, not reach the network.
        Http::preventStrayRequests();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // A minimal host-application users table; the package does not ship one.
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
