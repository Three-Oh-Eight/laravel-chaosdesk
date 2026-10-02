<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Webhooks;

/**
 * The event names ChaosDesk sends in the X-ChaosDesk-Event header and the
 * `event` key of a generic webhook delivery.
 *
 * Match on these instead of string literals:
 *
 *     match ($request->header(WebhookEvent::HEADER)) {
 *         WebhookEvent::COMMUNITY_POST_CREATED => ...,
 *         default => null,
 *     };
 *
 * The community events carry `board_slug` in their `metadata`, so a host
 * with several boards notifies the right audience.
 */
final class WebhookEvent
{
    /**
     * The request header carrying the event name.
     */
    public const HEADER = 'X-ChaosDesk-Event';

    /**
     * The request header carrying the unique delivery id.
     */
    public const DELIVERY_HEADER = 'X-ChaosDesk-Delivery';

    /**
     * The request header carrying the `sha256=<hex>` signature; see Signature.
     */
    public const SIGNATURE_HEADER = 'X-ChaosDesk-Signature';

    public const TICKET_CREATED = 'ticket.created';

    public const TICKET_REPLIED = 'ticket.replied';

    public const TICKET_STATUS_CHANGED = 'ticket.status_changed';

    public const TICKET_ASSIGNED = 'ticket.assigned';

    public const TICKET_SLA_WARNING = 'ticket.sla_warning';

    public const TICKET_SLA_BREACHED = 'ticket.sla_breached';

    /**
     * An agent moved a thread. Metadata: thread_ulid, board_slug, old_status,
     * new_status, decline_reason, title, author_external_id.
     */
    public const COMMUNITY_THREAD_STATUS_CHANGED = 'community.thread.status_changed';

    /**
     * An agent posted an official reply. Metadata: thread_ulid, board_slug,
     * post_ulid, title, excerpt, author_external_id (the thread author).
     */
    public const COMMUNITY_POST_CREATED = 'community.post.created';

    /**
     * A poll opened. Metadata: poll_ulid, board_slug, question, closes_at.
     */
    public const COMMUNITY_POLL_OPENED = 'community.poll.opened';

    /**
     * A poll closed. Metadata: poll_ulid, board_slug, question,
     * respondents_count, results (option_ulid, label, responses_count).
     */
    public const COMMUNITY_POLL_CLOSED = 'community.poll.closed';

    /**
     * The ticket events.
     *
     * @return list<string>
     */
    public static function ticket(): array
    {
        return [
            self::TICKET_CREATED,
            self::TICKET_REPLIED,
            self::TICKET_STATUS_CHANGED,
            self::TICKET_ASSIGNED,
            self::TICKET_SLA_WARNING,
            self::TICKET_SLA_BREACHED,
        ];
    }

    /**
     * The community events.
     *
     * @return list<string>
     */
    public static function community(): array
    {
        return [
            self::COMMUNITY_THREAD_STATUS_CHANGED,
            self::COMMUNITY_POST_CREATED,
            self::COMMUNITY_POLL_OPENED,
            self::COMMUNITY_POLL_CLOSED,
        ];
    }

    /**
     * Every event ChaosDesk sends.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [...self::ticket(), ...self::community()];
    }

    /**
     * Whether an event name is one of the community events.
     */
    public static function isCommunity(?string $event): bool
    {
        return in_array($event, self::community(), true);
    }
}
