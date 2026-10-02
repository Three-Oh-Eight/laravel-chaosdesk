{{--
    Styled with plain Tailwind so the polls render in any application.
    Publish these views to restyle: php artisan vendor:publish --tag=chaosdesk-views
--}}
@use('ThreeOhEight\ChaosDesk\Community\CommunityUrls')
@use('ThreeOhEight\ChaosDesk\Livewire\CommunityCharter')
<div class="w-full max-w-2xl text-zinc-900 dark:text-zinc-100">
    @guest
        <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
            {{ __('chaosdesk::community.guest') }}
        </p>
    @else
        {{-- Block form only: Blade cannot compile an inline php directive ahead of a php block. --}}
        @php
            $community = $this->communityBoard;
            $boardUrl = $embedded ? null : CommunityUrls::board($board, $site);
        @endphp

        @if ($community !== null && $community->needsCharterAcceptance())
            @livewire(CommunityCharter::class, ['board' => $board, 'site' => $site, 'embedded' => true], key('chaosdesk-charter-'.$board))
        @else
            @php
                $polls = $community === null ? null : $this->polls;
                $results = $polls === null ? [] : $this->results;
                $canWrite = $this->canWrite;
            @endphp

            <div class="space-y-6">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold">{{ __('chaosdesk::community.polls.heading') }}</h2>
                    @if ($embedded)
                        <button type="button" wire:click="back" class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100">
                            {{ __('chaosdesk::community.board.back') }}
                        </button>
                    @elseif ($boardUrl)
                        <a href="{{ $boardUrl }}" class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100">
                            {{ __('chaosdesk::community.board.back') }}
                        </a>
                    @endif
                </div>

                @include('chaosdesk::livewire.community.partials.error')

                @if ($community?->memberIsBlocked())
                    <p role="status" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        {{ __('chaosdesk::community.blocked') }}
                    </p>
                @endif

                @if ($polls !== null)
                    @forelse ($polls as $poll)
                        <section wire:key="chaosdesk-poll-{{ $poll->ulid }}" aria-labelledby="chaosdesk-poll-{{ $poll->ulid }}-question" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <h3 id="chaosdesk-poll-{{ $poll->ulid }}-question" class="text-sm font-semibold">{{ $poll->question }}</h3>
                                <span @class([
                                    'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' => $poll->isOpen,
                                    'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' => ! $poll->isOpen,
                                ])>{{ $poll->isOpen ? __('chaosdesk::community.polls.open') : __('chaosdesk::community.polls.closed') }}</span>
                            </div>

                            @if ($poll->closesAt)
                                <p class="mt-1 text-xs text-zinc-500">
                                    <time datetime="{{ $poll->closesAt->toIso8601String() }}">
                                        {{ $poll->isClosed
                                            ? __('chaosdesk::community.polls.closed_on', ['date' => $poll->closesAt->locale(app()->getLocale())->isoFormat('LL')])
                                            : __('chaosdesk::community.polls.closes', ['date' => $poll->closesAt->locale(app()->getLocale())->isoFormat('LLL')]) }}
                                    </time>
                                </p>
                            @endif

                            @if ($poll->isOpen)
                                <form wire:submit="respond('{{ $poll->ulid }}')" class="mt-3 space-y-3">
                                    <fieldset @error('answers.'.$poll->ulid) aria-describedby="chaosdesk-poll-{{ $poll->ulid }}-error" @enderror>
                                        <legend class="text-xs text-zinc-500">
                                            {{ $poll->isMultipleChoice ? __('chaosdesk::community.polls.multiple') : __('chaosdesk::community.polls.single') }}
                                        </legend>
                                        <div class="mt-2 space-y-2">
                                            @foreach ($poll->options as $option)
                                                <label wire:key="chaosdesk-poll-option-{{ $option->ulid }}" class="flex items-center gap-2 text-sm">
                                                    <input
                                                        type="{{ $poll->isMultipleChoice ? 'checkbox' : 'radio' }}"
                                                        name="chaosdesk-poll-{{ $poll->ulid }}"
                                                        value="{{ $option->ulid }}"
                                                        wire:model="answers.{{ $poll->ulid }}"
                                                        @disabled(! $canWrite)
                                                        class="h-4 w-4 border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-700"
                                                    />
                                                    <span>{{ $option->label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    @error('answers.'.$poll->ulid) <p id="chaosdesk-poll-{{ $poll->ulid }}-error" class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                    @if ($canWrite)
                                        <div class="flex items-center gap-3">
                                            <button
                                                type="submit"
                                                wire:loading.attr="disabled"
                                                class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                                            >
                                                <span wire:loading.remove wire:target="respond('{{ $poll->ulid }}')">{{ $poll->hasResponded() ? __('chaosdesk::community.polls.change') : __('chaosdesk::community.polls.submit') }}</span>
                                                <span wire:loading wire:target="respond('{{ $poll->ulid }}')">{{ __('chaosdesk::community.polls.submitting') }}</span>
                                            </button>
                                            @if ($savedPoll === $poll->ulid)
                                                <span role="status" class="text-sm text-green-700 dark:text-green-400">{{ __('chaosdesk::community.polls.saved') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </form>
                            @elseif (isset($results[$poll->ulid]))
                                @php($result = $results[$poll->ulid])
                                <div class="mt-3 space-y-3">
                                    <ul class="space-y-2">
                                        @foreach ($result->options as $option)
                                            @php($percent = $result->percentage($option))
                                            <li wire:key="chaosdesk-poll-result-{{ $option->ulid }}" class="text-sm">
                                                <div class="flex items-baseline justify-between gap-3">
                                                    <span @class(['font-medium' => $poll->picked($option->ulid)])>{{ $option->label }}</span>
                                                    <span class="shrink-0 text-xs text-zinc-500">{{ __('chaosdesk::community.polls.option_result', ['count' => $option->responsesCount ?? 0, 'percent' => rtrim(rtrim(number_format($percent, 1), '0'), '.')]) }}</span>
                                                </div>
                                                <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" aria-hidden="true">
                                                    <div class="h-full rounded-full bg-zinc-900 dark:bg-white" style="width: {{ min(100, max(0, $percent)) }}%"></div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <p class="text-xs text-zinc-500">{{ trans_choice('chaosdesk::community.polls.respondents', $result->respondentsCount) }}</p>
                                </div>
                            @else
                                <p class="mt-3 text-sm text-zinc-500">{{ __('chaosdesk::community.polls.results_pending') }}</p>
                            @endif
                        </section>
                    @empty
                        <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                            {{ __('chaosdesk::community.polls.empty') }}
                        </p>
                    @endforelse
                @endif
            </div>
        @endif
    @endguest
</div>
