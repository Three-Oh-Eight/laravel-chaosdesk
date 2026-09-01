<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Support;

/**
 * A local pointer to a ticket that lives in ChaosDesk.
 *
 * The access token is the credential for reading and replying to the thread,
 * which is why the reference is kept per user rather than shared.
 */
final readonly class TicketReference
{
    public function __construct(
        public string $ulid,
        public string $accessToken,
        public string $subject,
        public ?string $createdAt = null,
    ) {}

    /**
     * @return array{ulid: string, subject: string, created_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'ulid' => $this->ulid,
            'subject' => $this->subject,
            'created_at' => $this->createdAt,
        ];
    }
}
