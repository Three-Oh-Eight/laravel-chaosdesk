{{--
    Styled with plain Tailwind so the board renders in any application.
    Publish these views to restyle: php artisan vendor:publish --tag=chaosdesk-views
    Publish the strings to translate: php artisan vendor:publish --tag=chaosdesk-lang
--}}
@use('ThreeOhEight\ChaosDesk\Community\CommunityUrls')
@use('ThreeOhEight\ChaosDesk\Community\ThreadStatus')
@use('ThreeOhEight\ChaosDesk\Livewire\CommunityBoard')
@use('ThreeOhEight\ChaosDesk\Livewire\CommunityCharter')
@use('ThreeOhEight\ChaosDesk\Livewire\CommunityNewThread')
@use('ThreeOhEight\ChaosDesk\Livewire\CommunityPolls')
@use('ThreeOhEight\ChaosDesk\Livewire\CommunityThread')
@use('ThreeOhEight\ChaosDesk\Support\CommunityText')
<div class="w-full max-w-3xl text-zinc-900 dark:text-zinc-100">
    @guest
        <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
            {{ __('chaosdesk::community.guest') }}
        </p>
    @else
        {{-- Block form only: Blade cannot compile an inline php directive ahead of a php block. --}}
        @php
            $community = $this->communityBoard;
        @endphp

        @if ($community === null)
            @include('chaosdesk::livewire.community.partials.error')
        @elseif ($community->needsCharterAcceptance())
            @livewire(CommunityCharter::class, ['board' => $board, 'site' => $site, 'embedded' => true], key('chaosdesk-charter-'.$board))
        @elseif ($screen === 'thread' && $openThread)
            @livewire(CommunityThread::class, ['board' => $board, 'site' => $site, 'thread' => $openThread, 'embedded' => true], key('chaosdesk-thread-'.$openThread))
        @elseif ($screen === 'new')
            @livewire(CommunityNewThread::class, ['board' => $board, 'site' => $site, 'embedded' => true], key('chaosdesk-new-thread-'.$board))
        @elseif ($screen === 'polls')
            @livewire(CommunityPolls::class, ['board' => $board, 'site' => $site, 'embedded' => true], key('chaosdesk-polls-'.$board))
        @else
            @php
                $threads = $this->threads;
                $openPolls = $this->openPolls;
                $canWrite = $this->canWrite;
                $newThreadUrl = CommunityUrls::newThread($board, $site);
                $pollsUrl = CommunityUrls::polls($board, $site);
            @endphp

            <div class="space-y-6">
                <header class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $community->name }}</h2>
                        @if ($community->description)
                            <p class="mt-1 text-sm text-zinc-500">{{ $community->description }}</p>
                        @endif
                    </div>

                    @if ($canWrite && $community->allowedKinds !== [])
                        @if ($newThreadUrl)
                            <a href="{{ $newThreadUrl }}" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                                {{ __('chaosdesk::community.board.new_thread') }}
                            </a>
                        @else
                            <button type="button" wire:click="compose" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                                {{ __('chaosdesk::community.board.new_thread') }}
                            </button>
                        @endif
                    @endif
                </header>

                @include('chaosdesk::livewire.community.partials.error')

                @if ($community->memberIsBlocked())
                    <p role="status" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        {{ __('chaosdesk::community.blocked') }}
                    </p>
                @endif

                @if ($openPolls !== [])
                    <section aria-labelledby="chaosdesk-open-polls-{{ $board }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-4">
                            <h3 id="chaosdesk-open-polls-{{ $board }}" class="text-sm font-semibold">
                                {{ trans_choice('chaosdesk::community.board.open_polls', count($openPolls)) }}
                            </h3>
                            @if ($pollsUrl)
                                <a href="{{ $pollsUrl }}" class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100">{{ __('chaosdesk::community.board.view_polls') }}</a>
                            @else
                                <button type="button" wire:click="showPolls" class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100">{{ __('chaosdesk::community.board.view_polls') }}</button>
                            @endif
                        </div>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($openPolls as $poll)
                                <li wire:key="chaosdesk-open-poll-{{ $poll->ulid }}" class="flex items-center justify-between gap-3">
                                    <span>{{ $poll->question }}</span>
                                    @if ($poll->hasResponded())
                                        <span class="shrink-0 text-xs text-green-700 dark:text-green-400">{{ __('chaosdesk::community.board.answered') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <div role="group" aria-label="{{ __('chaosdesk::community.board.filters') }}" class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="chaosdesk-kind-{{ $board }}" class="block text-sm font-medium">{{ __('chaosdesk::community.board.kind') }}</label>
                        <select id="chaosdesk-kind-{{ $board }}" wire:model.live="kind" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="">{{ __('chaosdesk::community.board.all_kinds') }}</option>
                            @foreach ($community->allowedKinds as $allowedKind)
                                <option wire:key="chaosdesk-kind-{{ $allowedKind }}" value="{{ $allowedKind }}">{{ CommunityText::kind($allowedKind) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="chaosdesk-status-{{ $board }}" class="block text-sm font-medium">{{ __('chaosdesk::community.board.status') }}</label>
                        <select id="chaosdesk-status-{{ $board }}" wire:model.live="status" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="">{{ __('chaosdesk::community.board.all_statuses') }}</option>
                            @foreach (ThreadStatus::cases() as $case)
                                <option wire:key="chaosdesk-status-{{ $case->value }}" value="{{ $case->value }}">{{ CommunityText::status($case->value) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="chaosdesk-sort-{{ $board }}" class="block text-sm font-medium">{{ __('chaosdesk::community.board.sort') }}</label>
                        <select id="chaosdesk-sort-{{ $board }}" wire:model.live="sort" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach (CommunityBoard::SORTS as $option)
                                <option wire:key="chaosdesk-sort-{{ $option }}" value="{{ $option }}">{{ CommunityText::sort($option) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($threads !== null)
                    <div wire:loading.class="opacity-50" wire:target="kind,status,sort,previousPage,nextPage">
                        @if ($threads->isEmpty())
                            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                                {{ __('chaosdesk::community.board.empty') }}
                            </p>
                        @else
                            <ul class="space-y-2">
                                @foreach ($threads as $thread)
                                    @php($threadUrl = CommunityUrls::thread($board, $thread->ulid, $site))
                                    <li wire:key="chaosdesk-thread-{{ $thread->ulid }}" class="flex items-start gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                        @if ($thread->acceptsVotes)
                                            @include('chaosdesk::livewire.community.partials.vote', [
                                                'thread' => $thread,
                                                'action' => "toggleVote('{$thread->ulid}')",
                                                'canVote' => $canWrite,
                                            ])
                                        @endif

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if ($thread->isPinned)
                                                    <span class="text-xs font-medium text-zinc-500">{{ __('chaosdesk::community.board.pinned') }}</span>
                                                @endif
                                                <span class="text-xs text-zinc-500">{{ CommunityText::kind($thread->kind) }}</span>
                                                @include('chaosdesk::livewire.community.partials.status', ['status' => $thread->status])
                                                @if ($thread->isLocked)
                                                    <span class="text-xs text-zinc-500">{{ __('chaosdesk::community.board.locked') }}</span>
                                                @endif
                                            </div>

                                            <h3 class="mt-1 text-sm font-semibold">
                                                @if ($threadUrl)
                                                    <a href="{{ $threadUrl }}" class="hover:underline">{{ $thread->title }}</a>
                                                @else
                                                    <button type="button" wire:click="showThread('{{ $thread->ulid }}')" class="text-left hover:underline">{{ $thread->title }}</button>
                                                @endif
                                            </h3>

                                            <p class="mt-1 text-xs text-zinc-500">
                                                @if ($thread->authorName)
                                                    {{ __('chaosdesk::community.board.by', ['name' => $thread->authorName]) }} ·
                                                @endif
                                                {{ trans_choice('chaosdesk::community.board.replies', $thread->postsCount) }}
                                                @if ($thread->lastActivityAt)
                                                    · <time datetime="{{ $thread->lastActivityAt->toIso8601String() }}">{{ $thread->lastActivityAt->locale(app()->getLocale())->diffForHumans() }}</time>
                                                @endif
                                            </p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    @if ($threads->lastPage > 1)
                        <nav aria-label="{{ __('chaosdesk::community.board.pagination') }}" class="flex items-center justify-between gap-4 text-sm">
                            <button type="button" wire:click="previousPage" @disabled($threads->currentPage <= 1) class="rounded-lg border border-zinc-300 px-3 py-1.5 transition hover:border-zinc-500 disabled:opacity-50 dark:border-zinc-700">
                                {{ __('chaosdesk::community.board.previous') }}
                            </button>
                            <span class="text-zinc-500">{{ __('chaosdesk::community.board.page', ['current' => $threads->currentPage, 'last' => $threads->lastPage]) }}</span>
                            <button type="button" wire:click="nextPage" @disabled(! $threads->hasMorePages()) class="rounded-lg border border-zinc-300 px-3 py-1.5 transition hover:border-zinc-500 disabled:opacity-50 dark:border-zinc-700">
                                {{ __('chaosdesk::community.board.next') }}
                            </button>
                        </nav>
                    @endif
                @endif
            </div>
        @endif
    @endguest
</div>
