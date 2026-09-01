<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Contracts\TicketStore;
use ThreeOhEight\ChaosDesk\Livewire\SupportForm;
use ThreeOhEight\ChaosDesk\Livewire\TicketList;
use ThreeOhEight\ChaosDesk\Storage\DatabaseTicketStore;

class ChaosDeskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chaosdesk.php', 'chaosdesk');

        $this->app->singleton(ChaosDesk::class);

        $this->app->bind(TicketStore::class, DatabaseTicketStore::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'chaosdesk');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'chaosdesk');

        $this->registerComponents();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/chaosdesk.php' => config_path('chaosdesk.php'),
            ], 'chaosdesk-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/chaosdesk'),
            ], 'chaosdesk-views');

            $this->publishes([
                __DIR__.'/../resources/js/chaosdesk.js' => resource_path('js/chaosdesk.js'),
            ], 'chaosdesk-assets');
        }
    }

    protected function registerComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component((string) config('chaosdesk.components.support', 'chaosdesk-support'), SupportForm::class);
        Livewire::component((string) config('chaosdesk.components.tickets', 'chaosdesk-tickets'), TicketList::class);
    }
}
