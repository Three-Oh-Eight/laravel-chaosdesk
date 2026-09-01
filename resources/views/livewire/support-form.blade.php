{{--
    Styled with plain Tailwind so the form renders in any application.
    Publish these views to restyle: php artisan vendor:publish --tag=chaosdesk-views
--}}
<div
    x-data="chaosdeskSupport()"
    class="w-full max-w-xl text-zinc-900 dark:text-zinc-100"
>
    @if ($submitted)
        <div class="rounded-xl border border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-950">
            <h2 class="text-lg font-semibold text-green-900 dark:text-green-100">
                {{ __('Thanks, we have your message') }}
            </h2>
            <p class="mt-2 text-sm text-green-800 dark:text-green-200">
                {{ __('We will reply by email. You can keep using the app in the meantime.') }}
            </p>
            <button
                type="button"
                wire:click="startOver"
                class="mt-4 rounded-lg border border-green-300 px-3 py-1.5 text-sm font-medium text-green-900 transition hover:bg-green-100 dark:border-green-800 dark:text-green-100 dark:hover:bg-green-900"
            >{{ __('Send another') }}</button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            @if ($error)
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                    {{ $error }}
                </div>
            @endif

            @guest
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="chaosdesk-name" class="block text-sm font-medium">{{ __('Your name') }}</label>
                        <input
                            id="chaosdesk-name"
                            type="text"
                            wire:model="name"
                            class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                        />
                        @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="chaosdesk-email" class="block text-sm font-medium">{{ __('Email') }}</label>
                        <input
                            id="chaosdesk-email"
                            type="email"
                            wire:model="email"
                            required
                            class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                        />
                        @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endguest

            @if (! empty($this->siteConfig['categories']))
                <div>
                    <label for="chaosdesk-category" class="block text-sm font-medium">{{ __('Topic') }}</label>
                    <select
                        id="chaosdesk-category"
                        wire:model="categoryId"
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <option value="">{{ __('Choose a topic') }}</option>
                        @foreach ($this->siteConfig['categories'] as $category)
                            <option wire:key="category-{{ $category['id'] }}" value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="chaosdesk-subject" class="block text-sm font-medium">{{ __('Subject') }}</label>
                <input
                    id="chaosdesk-subject"
                    type="text"
                    wire:model="subject"
                    required
                    class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                />
                @error('subject') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="chaosdesk-message" class="block text-sm font-medium">{{ __('What happened?') }}</label>
                <textarea
                    id="chaosdesk-message"
                    wire:model="message"
                    rows="5"
                    required
                    class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900"
                ></textarea>
                @error('message') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            @if (config('chaosdesk.attachments.enabled'))
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                    @if ($screenshot)
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $screenshot }}" alt="{{ __('Screenshot preview') }}" class="h-12 w-20 rounded border border-zinc-200 object-cover dark:border-zinc-700" />
                                <span class="text-sm">{{ __('Screenshot attached') }}</span>
                            </div>
                            <button
                                type="button"
                                wire:click="removeScreenshot"
                                class="text-sm text-zinc-500 underline hover:text-zinc-900 dark:hover:text-zinc-100"
                            >{{ __('Remove') }}</button>
                        </div>
                    @else
                        <button
                            type="button"
                            x-on:click="captureScreenshot"
                            x-bind:disabled="capturing"
                            class="text-sm font-medium text-zinc-700 underline underline-offset-2 hover:text-zinc-900 disabled:opacity-50 dark:text-zinc-300 dark:hover:text-zinc-100"
                        >
                            <span x-show="! capturing">{{ __('Attach a screenshot') }}</span>
                            <span x-show="capturing" x-cloak>{{ __('Waiting for the browser…') }}</span>
                        </button>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ __('Your browser will ask which window or tab to share. Nothing is captured until you choose.') }}
                        </p>
                        <p x-show="captureError" x-cloak x-text="captureError" class="mt-1 text-xs text-red-600 dark:text-red-400"></p>
                    @endif
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                    <span wire:loading.remove wire:target="submit">{{ __('Send') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
                </button>

                <span class="text-xs text-zinc-500">
                    {{ __('We include your page, app version and recent errors to help us diagnose faster.') }}
                </span>
            </div>
        </form>
    @endif
</div>
