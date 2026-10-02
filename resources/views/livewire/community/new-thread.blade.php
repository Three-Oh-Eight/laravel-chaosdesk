{{--
    Styled with plain Tailwind so the form renders in any application.
    Publish these views to restyle: php artisan vendor:publish --tag=chaosdesk-views
--}}
@use('ThreeOhEight\ChaosDesk\Community\CommunityUrls')
@use('ThreeOhEight\ChaosDesk\Livewire\CommunityCharter')
@use('ThreeOhEight\ChaosDesk\Support\CommunityText')
<div class="w-full max-w-2xl text-zinc-900 dark:text-zinc-100">
    @guest
        <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
            {{ __('chaosdesk::community.guest') }}
        </p>
    @else
        @php($community = $this->communityBoard)
        @php($boardUrl = $embedded ? null : CommunityUrls::board($board, $site))

        @if ($community === null)
            @include('chaosdesk::livewire.community.partials.error')
        @elseif ($community->needsCharterAcceptance())
            @livewire(CommunityCharter::class, ['board' => $board, 'site' => $site, 'embedded' => true], key('chaosdesk-charter-'.$board))
        @elseif ($createdThread)
            <div role="status" class="rounded-xl border border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-950">
                <p class="text-sm font-medium text-green-900 dark:text-green-100">{{ __('chaosdesk::community.new_thread.posted') }}</p>
                <button
                    type="button"
                    wire:click="startOver"
                    class="mt-4 rounded-lg border border-green-300 px-3 py-1.5 text-sm font-medium text-green-900 transition hover:bg-green-100 dark:border-green-800 dark:text-green-100 dark:hover:bg-green-900"
                >{{ __('chaosdesk::community.new_thread.another') }}</button>
            </div>
        @elseif ($community->memberIsBlocked())
            <p role="status" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                {{ __('chaosdesk::community.blocked') }}
            </p>
        @elseif ($community->allowedKinds === [])
            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                {{ __('chaosdesk::community.new_thread.no_kinds') }}
            </p>
        @else
            <form wire:submit="submit" class="space-y-4">
                <h2 class="text-lg font-semibold">{{ __('chaosdesk::community.new_thread.heading') }}</h2>

                @include('chaosdesk::livewire.community.partials.error')

                <div>
                    <label for="chaosdesk-new-thread-kind-{{ $board }}" class="block text-sm font-medium">{{ __('chaosdesk::community.new_thread.kind') }}</label>
                    <select
                        id="chaosdesk-new-thread-kind-{{ $board }}"
                        wire:model="kind"
                        required
                        @error('kind') aria-invalid="true" aria-describedby="chaosdesk-new-thread-kind-error" @enderror
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        @foreach ($community->allowedKinds as $allowedKind)
                            <option wire:key="chaosdesk-new-thread-kind-{{ $allowedKind }}" value="{{ $allowedKind }}">{{ CommunityText::kind($allowedKind) }}</option>
                        @endforeach
                    </select>
                    @error('kind') <p id="chaosdesk-new-thread-kind-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="chaosdesk-new-thread-title-{{ $board }}" class="block text-sm font-medium">{{ __('chaosdesk::community.new_thread.title') }}</label>
                    <input
                        id="chaosdesk-new-thread-title-{{ $board }}"
                        type="text"
                        wire:model="title"
                        required
                        maxlength="200"
                        @error('title') aria-invalid="true" aria-describedby="chaosdesk-new-thread-title-error" @enderror
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                    />
                    @error('title') <p id="chaosdesk-new-thread-title-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="chaosdesk-new-thread-body-{{ $board }}" class="block text-sm font-medium">{{ __('chaosdesk::community.new_thread.body') }}</label>
                    <textarea
                        id="chaosdesk-new-thread-body-{{ $board }}"
                        wire:model="body"
                        rows="6"
                        required
                        maxlength="10000"
                        @error('body') aria-invalid="true" aria-describedby="chaosdesk-new-thread-body-error" @enderror
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                    ></textarea>
                    @error('body') <p id="chaosdesk-new-thread-body-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                    >
                        <span wire:loading.remove wire:target="submit">{{ __('chaosdesk::community.new_thread.submit') }}</span>
                        <span wire:loading wire:target="submit">{{ __('chaosdesk::community.new_thread.submitting') }}</span>
                    </button>

                    @if ($embedded)
                        <button type="button" wire:click="back" class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100">
                            {{ __('chaosdesk::community.new_thread.cancel') }}
                        </button>
                    @elseif ($boardUrl)
                        <a href="{{ $boardUrl }}" class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100">
                            {{ __('chaosdesk::community.new_thread.cancel') }}
                        </a>
                    @endif
                </div>
            </form>
        @endif
    @endguest
</div>
