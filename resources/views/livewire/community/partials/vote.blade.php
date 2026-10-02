{{--
    The upvote toggle of a proposal. Expects $thread (Community\Data\Thread),
    $action (the wire:click expression) and $canVote.
--}}
<button
    type="button"
    wire:click="{{ $action }}"
    wire:loading.attr="disabled"
    aria-pressed="{{ $thread->hasVoted ? 'true' : 'false' }}"
    @disabled(! $canVote)
    @class([
        'flex w-14 shrink-0 flex-col items-center rounded-lg border px-2 py-1.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50',
        'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $thread->hasVoted,
        'border-zinc-300 text-zinc-700 hover:border-zinc-500 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-500' => ! $thread->hasVoted,
    ])
>
    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 5a1 1 0 0 1 .7.3l5 5a1 1 0 0 1-1.4 1.4L10 7.4l-4.3 4.3a1 1 0 0 1-1.4-1.4l5-5A1 1 0 0 1 10 5Z" clip-rule="evenodd" />
    </svg>
    <span aria-hidden="true">{{ $thread->votesCount }}</span>
    <span class="sr-only">
        {{ $thread->hasVoted
            ? __('chaosdesk::community.vote.remove', ['title' => $thread->title])
            : __('chaosdesk::community.vote.add', ['title' => $thread->title]) }},
        {{ trans_choice('chaosdesk::community.vote.count', $thread->votesCount) }}
    </span>
</button>
