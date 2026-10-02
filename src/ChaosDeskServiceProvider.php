<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Contracts\TicketStore;
use ThreeOhEight\ChaosDesk\Livewire\CommunityBoard;
use ThreeOhEight\ChaosDesk\Livewire\CommunityCharter;
use ThreeOhEight\ChaosDesk\Livewire\CommunityNewThread;
use ThreeOhEight\ChaosDesk\Livewire\CommunityPolls;
use ThreeOhEight\ChaosDesk\Livewire\CommunityThread;
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
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'chaosdesk');

        if ((bool) config('chaosdesk.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->registerComponents();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/chaosdesk.php' => config_path('chaosdesk.php'),
            ], 'chaosdesk-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'chaosdesk-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/chaosdesk'),
            ], 'chaosdesk-views');

            $this->publishes([
                __DIR__.'/../resources/js/chaosdesk.js' => resource_path('js/chaosdesk.js'),
            ], 'chaosdesk-assets');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/chaosdesk'),
            ], 'chaosdesk-lang');
        }
    }

    protected function registerComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component((string) config('chaosdesk.components.support', 'chaosdesk-support'), SupportForm::class);
        Livewire::component((string) config('chaosdesk.components.tickets', 'chaosdesk-tickets'), TicketList::class);

        // A config published before 1.2 has no community names; the defaults apply.
        Livewire::component((string) config('chaosdesk.components.community_board', 'chaosdesk-community-board'), CommunityBoard::class);
        Livewire::component((string) config('chaosdesk.components.community_thread', 'chaosdesk-community-thread'), CommunityThread::class);
        Livewire::component((string) config('chaosdesk.components.community_new_thread', 'chaosdesk-community-new-thread'), CommunityNewThread::class);
        Livewire::component((string) config('chaosdesk.components.community_polls', 'chaosdesk-community-polls'), CommunityPolls::class);
        Livewire::component((string) config('chaosdesk.components.community_charter', 'chaosdesk-community-charter'), CommunityCharter::class);
    }
}
