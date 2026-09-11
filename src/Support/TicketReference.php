<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Support;

/**
 * A local pointer to a ticket that lives in ChaosDesk.
 *
 * The access token is the credential for reading and replying to the thread,
 * which is why the reference is kept per user rather than shared. The site
 * names which ChaosDesk site owns the ticket; the id is its numeric ticket
 * number there, kept for display only.
 */
final readonly class TicketReference
{
    public function __construct(
        public string $ulid,
        public string $accessToken,
        public string $subject,
        public ?string $createdAt = null,
        public string $site = 'default',
        public ?int $id = null,
    ) {}

    /**
     * @return array{ulid: string, subject: string, created_at: string|null, site: string, id: int|null}
     */
    public function toArray(): array
    {
        return [
            'ulid' => $this->ulid,
            'subject' => $this->subject,
            'created_at' => $this->createdAt,
            'site' => $this->site,
            'id' => $this->id,
        ];
    }
}
