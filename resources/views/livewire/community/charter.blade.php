{{--
    Styled with plain Tailwind so the charter renders in any application.
    The markdown is rendered with raw HTML escaped and unsafe links dropped.
    Publish these views to restyle: php artisan vendor:publish --tag=chaosdesk-views
--}}
@use('ThreeOhEight\ChaosDesk\Community\CommunityUrls')
<div class="w-full max-w-2xl text-zinc-900 dark:text-zinc-100">
    @guest
        <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
            {{ __('chaosdesk::community.guest') }}
        </p>
    @else
        @php($community = $this->communityBoard)
        @php($boardUrl = $embedded ? null : CommunityUrls::board($board, $site))

        <section aria-labelledby="chaosdesk-charter-{{ $board }}" class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-800">
            <header>
                <h2 id="chaosdesk-charter-{{ $board }}" class="text-lg font-semibold">{{ __('chaosdesk::community.charter.heading') }}</h2>
                @if ($community?->charter)
                    <p class="mt-1 text-xs text-zinc-500">{{ __('chaosdesk::community.charter.version', ['version' => $community->charter->version]) }}</p>
                @endif
            </header>

            @include('chaosdesk::livewire.community.partials.error')

            @if ($community !== null)
                @if ($accepted)
                    <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        {{ __('chaosdesk::community.charter.accepted') }}
                        @if ($boardUrl)
                            <a href="{{ $boardUrl }}" class="ml-1 font-medium underline">{{ __('chaosdesk::community.charter.continue') }}</a>
                        @endif
                    </div>
                @elseif (! $community->needsCharterAcceptance())
                    <p class="text-sm text-zinc-500">{{ __('chaosdesk::community.charter.already_accepted') }}</p>
                @else
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('chaosdesk::community.charter.intro') }}</p>
                @endif

                @if ($this->charterHtml !== '')
                    <div class="max-h-[32rem] overflow-y-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm leading-relaxed dark:border-zinc-800 dark:bg-zinc-900 [&_a]:underline [&_h1]:mb-2 [&_h1]:text-base [&_h1]:font-semibold [&_h2]:mb-2 [&_h2]:mt-4 [&_h2]:font-semibold [&_h3]:mt-3 [&_h3]:font-medium [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-3 [&_ul]:list-disc" role="region" aria-labelledby="chaosdesk-charter-{{ $board }}" tabindex="0">
                        {!! $this->charterHtml !!}
                    </div>
                @else
                    <p class="text-sm text-zinc-500">{{ __('chaosdesk::community.charter.empty') }}</p>
                @endif

                @if (! $accepted && $community->needsCharterAcceptance())
                    <button
                        type="button"
                        wire:click="accept"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                    >
                        <span wire:loading.remove wire:target="accept">{{ __('chaosdesk::community.charter.accept') }}</span>
                        <span wire:loading wire:target="accept">{{ __('chaosdesk::community.charter.accepting') }}</span>
                    </button>
                @endif
            @endif
        </section>
    @endguest
</div>
