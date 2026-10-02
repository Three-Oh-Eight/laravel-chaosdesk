{{--
    Styled with plain Tailwind so the thread renders in any application.
    Publish these views to restyle: php artisan vendor:publish --tag=chaosdesk-views
--}}
@use('ThreeOhEight\ChaosDesk\Community\CommunityUrls')
@use('ThreeOhEight\ChaosDesk\Livewire\CommunityCharter')
@use('ThreeOhEight\ChaosDesk\Support\CommunityText')
<div class="w-full max-w-3xl text-zinc-900 dark:text-zinc-100">
    @guest
        <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
            {{ __('chaosdesk::community.guest') }}
        </p>
    @else
        @php($community = $this->communityBoard)
        @php($boardUrl = $embedded ? null : CommunityUrls::board($board, $site))

        @if ($community !== null && $community->needsCharterAcceptance())
            @livewire(CommunityCharter::class, ['board' => $board, 'site' => $site, 'embedded' => true], key('chaosdesk-charter-'.$board))
        @else
            @php($detail = $community === null ? null : $this->detail)

            <div class="space-y-6">
                @if ($embedded)
                    <button type="button" wire:click="back" class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100">
                        {{ __('chaosdesk::community.board.back') }}
                    </button>
                @elseif ($boardUrl)
                    <a href="{{ $boardUrl }}" class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100">
                        {{ __('chaosdesk::community.board.back') }}
                    </a>
                @endif

                @include('chaosdesk::livewire.community.partials.error')

                @if ($detail !== null)
                    @php($canWrite = $this->canWrite)

                    <article class="space-y-6">
                        <header class="flex items-start gap-4">
                            @if ($detail->acceptsVotes)
                                @include('chaosdesk::livewire.community.partials.vote', [
                                    'thread' => $detail,
                                    'action' => 'toggleVote',
                                    'canVote' => $canWrite,
                                ])
                            @endif

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-zinc-500">{{ CommunityText::kind($detail->kind) }}</span>
                                    @include('chaosdesk::livewire.community.partials.status', ['status' => $detail->status])
                                    @if ($detail->isLocked)
                                        <span class="text-xs text-zinc-500">{{ __('chaosdesk::community.board.locked') }}</span>
                                    @endif
                                </div>
                                <h2 class="mt-1 text-lg font-semibold">{{ $detail->title }}</h2>
                                <p class="mt-1 text-xs text-zinc-500">
                                    @if ($detail->authorName)
                                        {{ __('chaosdesk::community.thread.started_by', ['name' => $detail->authorName]) }}
                                    @endif
                                    @if ($detail->createdAt)
                                        · <time datetime="{{ $detail->createdAt->toIso8601String() }}">{{ $detail->createdAt->locale(app()->getLocale())->diffForHumans() }}</time>
                                    @endif
                                </p>
                            </div>
                        </header>

                        @if ($detail->hasStatus('declined') && $detail->declineReason)
                            <p class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                                {{ __('chaosdesk::community.thread.declined_reason', ['reason' => $detail->declineReason]) }}
                            </p>
                        @endif

                        <p class="whitespace-pre-wrap text-sm">{{ $detail->body }}</p>

                        <section aria-labelledby="chaosdesk-replies-{{ $detail->ulid }}" class="space-y-3">
                            <h3 id="chaosdesk-replies-{{ $detail->ulid }}" class="text-sm font-semibold">{{ __('chaosdesk::community.thread.replies') }}</h3>

                            @forelse ($detail->posts as $post)
                                <div
                                    wire:key="chaosdesk-post-{{ $post->ulid }}"
                                    @class([
                                        'rounded-xl border p-4',
                                        'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900' => ! $post->isOfficial,
                                        'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950' => $post->isOfficial,
                                    ])
                                >
                                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                                        <span class="flex items-center gap-2 text-sm font-medium">
                                            {{ $post->isMine ? __('chaosdesk::community.thread.you') : ($post->authorName ?? __('chaosdesk::community.thread.team')) }}
                                            @if ($post->isOfficial)
                                                <span class="rounded-full bg-blue-600 px-2 py-0.5 text-xs font-medium text-white dark:bg-blue-500">{{ __('chaosdesk::community.thread.official') }}</span>
                                            @elseif ($post->isAgent)
                                                <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ __('chaosdesk::community.thread.team') }}</span>
                                            @endif
                                        </span>
                                        @if ($post->createdAt)
                                            <time datetime="{{ $post->createdAt->toIso8601String() }}" class="text-xs text-zinc-500">{{ $post->createdAt->locale(app()->getLocale())->diffForHumans() }}</time>
                                        @endif
                                    </div>
                                    <p class="mt-2 whitespace-pre-wrap text-sm">{{ $post->body }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">{{ __('chaosdesk::community.thread.no_replies') }}</p>
                            @endforelse
                        </section>

                        @if ($detail->isLocked)
                            <p role="status" class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                                {{ __('chaosdesk::community.thread.locked') }}
                            </p>
                        @elseif ($community->memberIsBlocked())
                            <p role="status" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                {{ __('chaosdesk::community.blocked') }}
                            </p>
                        @else
                            <form wire:submit="sendReply" class="space-y-2">
                                <label for="chaosdesk-community-reply-{{ $detail->ulid }}" class="block text-sm font-medium">{{ __('chaosdesk::community.thread.reply') }}</label>
                                <textarea
                                    id="chaosdesk-community-reply-{{ $detail->ulid }}"
                                    wire:model="reply"
                                    rows="4"
                                    required
                                    maxlength="10000"
                                    @error('reply') aria-invalid="true" aria-describedby="chaosdesk-community-reply-error" @enderror
                                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                                ></textarea>
                                @error('reply') <p id="chaosdesk-community-reply-error" class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                                >
                                    <span wire:loading.remove wire:target="sendReply">{{ __('chaosdesk::community.thread.send_reply') }}</span>
                                    <span wire:loading wire:target="sendReply">{{ __('chaosdesk::community.thread.sending') }}</span>
                                </button>
                            </form>
                        @endif
                    </article>
                @endif
            </div>
        @endif
    @endguest
</div>
