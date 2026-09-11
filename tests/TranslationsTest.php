<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use ThreeOhEight\ChaosDesk\Livewire\SupportForm;
use ThreeOhEight\ChaosDesk\Livewire\TicketList;
use ThreeOhEight\ChaosDesk\Tests\Support\User;

it('loads the chaosdesk translation namespace', function (): void {
    expect(trans()->has('chaosdesk::chaosdesk.tickets.empty'))->toBeTrue()
        ->and(__('chaosdesk::chaosdesk.tickets.empty'))->toBe('You have not raised any support tickets yet.')
        ->and(__('chaosdesk::chaosdesk.form.message'))->toBe('What happened?');
});

it('ships every string the components use as a translation key', function (): void {
    $strings = require __DIR__.'/../resources/lang/en/chaosdesk.php';

    expect($strings)->toHaveKeys(['form', 'tickets'])
        ->and($strings['form'])->each->toBeString()
        ->and($strings['tickets'])->each->toBeString();
});

it('renders the ticket list empty state from its translation key', function (): void {
    fakeChaosDesk();

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    Livewire::actingAs($user)
        ->test(TicketList::class)
        ->assertOk()
        ->assertSee('You have not raised any support tickets yet.')
        ->assertDontSee('chaosdesk::');
});

it('renders the support form labels from their translation keys', function (): void {
    fakeChaosDesk();

    Livewire::test(SupportForm::class)
        ->assertOk()
        ->assertSee('What happened?')
        ->assertSee('Your name')
        ->assertSee('Attach a screenshot')
        ->assertDontSee('chaosdesk::');
});

it('honours a host override of a translated string', function (): void {
    fakeChaosDesk();

    app('translator')->addLines(['chaosdesk.tickets.empty' => 'Nothing here yet.'], 'en', 'chaosdesk');

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']);

    Livewire::actingAs($user)
        ->test(TicketList::class)
        ->assertSee('Nothing here yet.')
        ->assertDontSee('You have not raised any support tickets yet.');
});

it('publishes the strings under the chaosdesk-lang tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(null, 'chaosdesk-lang');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('resources/lang')
        ->and(reset($paths))->toEndWith('vendor/chaosdesk');
});
