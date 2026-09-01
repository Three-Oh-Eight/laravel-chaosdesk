<div class="w-full max-w-2xl text-zinc-900 dark:text-zinc-100">
    @if ($error)
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            {{ $error }}
        </div>
    @endif

    @if ($openUlid && $this->thread)
        @php($thread = $this->thread)

        <div class="space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ $thread['subject'] }}</h2>
                    <p class="text-sm text-zinc-500">{{ ucfirst($thread['status'] ?? '') }}</p>
                </div>
                <button
                    type="button"
                    wire:click="close"
                    class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100"
                >{{ __('Back to all tickets') }}</button>
            </div>

            <div class="space-y-3">
                @foreach ($thread['messages'] ?? [] as $message)
                    <div
                        wire:key="message-{{ $message['id'] }}"
                        @class([
                            'rounded-xl border p-4',
                            'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900' => empty($message['is_agent']),
                            'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950' => ! empty($message['is_agent']),
                        ])
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-sm font-medium">{{ $message['author_name'] ?? __('Support') }}</span>
                            <span class="text-xs text-zinc-500">{{ $message['created_at'] ?? '' }}</span>
                        </div>
                        <p class="mt-2 whitespace-pre-wrap text-sm">{{ $message['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <form wire:submit="sendReply" class="space-y-2">
                <label for="chaosdesk-reply" class="block text-sm font-medium">{{ __('Reply') }}</label>
                <textarea
                    id="chaosdesk-reply"
                    wire:model="reply"
                    rows="4"
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                ></textarea>
                @error('reply') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                    <span wire:loading.remove wire:target="sendReply">{{ __('Send reply') }}</span>
                    <span wire:loading wire:target="sendReply">{{ __('Sending…') }}</span>
                </button>
            </form>
        </div>
    @else
        @forelse ($this->tickets as $ticket)
            <button
                type="button"
                wire:key="ticket-{{ $ticket->ulid }}"
                wire:click="open('{{ $ticket->ulid }}')"
                class="mb-2 flex w-full items-center justify-between gap-4 rounded-xl border border-zinc-200 p-4 text-left transition hover:border-zinc-400 dark:border-zinc-800 dark:hover:border-zinc-600"
            >
                <span class="text-sm font-medium">{{ $ticket->subject }}</span>
                <span class="shrink-0 text-xs text-zinc-500">{{ $ticket->createdAt }}</span>
            </button>
        @empty
            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                {{ __('You have not raised any support tickets yet.') }}
            </p>
        @endforelse
    @endif
</div>
