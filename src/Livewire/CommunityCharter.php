<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use ThreeOhEight\ChaosDesk\Community\Data\Board;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Exceptions\CommunityException;
use ThreeOhEight\ChaosDesk\Livewire\Concerns\InteractsWithCommunity;
use ThreeOhEight\ChaosDesk\Support\CommunityText;

/**
 * The board's charter with an accept button.
 *
 * Accepting sends the version the member was shown. When the charter changed
 * in the meantime ChaosDesk refuses with `charter_version_mismatch`; the
 * component then shows the new text and asks again. On success it dispatches
 * `chaosdesk-community-charter-accepted` with the board slug.
 *
 * @property-read Board|null $communityBoard
 * @property-read bool $canWrite
 */
class CommunityCharter extends Component
{
    use InteractsWithCommunity;

    /**
     * The charter version on screen, recorded at every render.
     */
    #[Locked]
    public ?int $version = null;

    public bool $accepted = false;

    /**
     * The charter as HTML, with any raw HTML in the markdown escaped.
     */
    public function getCharterHtmlProperty(): string
    {
        return CommunityText::markdown($this->communityBoard?->charter?->markdown);
    }

    public function accept(): void
    {
        $community = $this->communityForAction();

        if ($community === null) {
            return;
        }

        try {
            $community->acceptCharter($this->board, $this->version);
        } catch (ChaosDeskException $e) {
            $this->fail($e);

            if ($e instanceof CommunityException && $e->is(CommunityException::CHARTER_VERSION_MISMATCH)) {
                $this->error = (string) __('chaosdesk::community.charter.changed');
            }

            unset($this->communityBoard, $this->charterHtml);

            return;
        }

        $this->accepted = true;
        unset($this->communityBoard, $this->charterHtml);

        $this->dispatch('chaosdesk-community-charter-accepted', board: $this->board);
    }

    public function render(): View
    {
        $this->version = $this->communityBoard?->charter?->version;

        return view('chaosdesk::livewire.community.charter');
    }
}
