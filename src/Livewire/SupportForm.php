<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Contracts\TicketStore;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Support\Identity;

/**
 * A support form that collects the ticket and everything needed to solve it.
 *
 * Diagnostic context is gathered on both sides: the browser reports the page,
 * viewport and recent console errors through the Alpine helper, and the server
 * adds the application version, runtime and authenticated user.
 */
class SupportForm extends Component
{
    #[Validate('required|string|max:255')]
    public string $subject = '';

    #[Validate('required|string|max:10000')]
    public string $message = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:255')]
    public string $name = '';

    public ?int $categoryId = null;

    public ?int $priorityId = null;

    public bool $submitted = false;

    public ?string $error = null;

    /**
     * Diagnostic context reported by the browser.
     *
     * @var array<string, mixed>
     */
    public array $clientContext = [];

    /**
     * A screenshot as a data URL, captured through the Screen Capture API.
     */
    public ?string $screenshot = null;

    public function mount(): void
    {
        $user = Auth::user();

        $this->email = Identity::email($user) ?? '';
        $this->name = Identity::name($user) ?? '';
    }

    /**
     * Categories and priorities offered by the site, if the API is reachable.
     *
     * @return array<string, mixed>
     */
    public function getSiteConfigProperty(): array
    {
        if (! app(ChaosDesk::class)->isConfigured()) {
            return [];
        }

        try {
            return app(ChaosDesk::class)->config();
        } catch (ChaosDeskException) {
            return [];
        }
    }

    public function submit(ChaosDesk $chaosDesk, TicketStore $store): void
    {
        $this->error = null;
        $this->validate();

        $user = Auth::user();
        $email = $this->email ?: Identity::email($user);

        if (! $email) {
            $this->addError('email', __('An email address is required.'));

            return;
        }

        try {
            $result = $chaosDesk->createTicket(
                attributes: array_filter([
                    'email' => $email,
                    'name' => $this->name ?: Identity::name($user),
                    'subject' => $this->subject,
                    'message' => $this->message,
                    'category_id' => $this->categoryId,
                    'priority_id' => $this->priorityId,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
                clientContext: $this->clientContext,
                user: $user,
            );
        } catch (ChaosDeskException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->attachScreenshot($chaosDesk, $result);

        if (($externalId = Identity::externalId($user)) !== null) {
            $store->remember($externalId, [
                'ulid' => $result['ticket']['ulid'],
                'access_token' => $result['access_token'],
                'subject' => $result['ticket']['subject'],
            ]);
        }

        $this->submitted = true;
        $this->reset(['subject', 'message', 'screenshot', 'clientContext']);

        $this->dispatch('chaosdesk-ticket-created', ulid: $result['ticket']['ulid']);
    }

    public function startOver(): void
    {
        $this->submitted = false;
        $this->error = null;
    }

    public function removeScreenshot(): void
    {
        $this->screenshot = null;
    }

    public function render(): View
    {
        return view('chaosdesk::livewire.support-form');
    }

    /**
     * Upload the captured screenshot, if there is one.
     *
     * A failed upload must not lose the ticket, which has already been created.
     *
     * @param  array<string, mixed>  $result
     */
    protected function attachScreenshot(ChaosDesk $chaosDesk, array $result): void
    {
        if ($this->screenshot === null || ! config('chaosdesk.attachments.enabled')) {
            return;
        }

        $contents = $this->decodeScreenshot($this->screenshot);

        if ($contents === null) {
            return;
        }

        try {
            $chaosDesk->attachContents(
                $result['ticket']['ulid'],
                $result['access_token'],
                $contents,
                'screenshot.png',
            );
        } catch (ChaosDeskException) {
            // The ticket exists; a missing screenshot is not worth failing over.
        }
    }

    /**
     * Decode a PNG data URL into raw bytes.
     */
    protected function decodeScreenshot(string $dataUrl): ?string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            return null;
        }

        $decoded = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        return $decoded === false ? null : $decoded;
    }
}
