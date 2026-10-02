<?php

declare(strict_types=1);

use ThreeOhEight\ChaosDesk\Webhooks\Signature;
use ThreeOhEight\ChaosDesk\Webhooks\WebhookEvent;

it('names the community events exactly as ChaosDesk sends them', function (): void {
    expect(WebhookEvent::COMMUNITY_THREAD_STATUS_CHANGED)->toBe('community.thread.status_changed')
        ->and(WebhookEvent::COMMUNITY_POST_CREATED)->toBe('community.post.created')
        ->and(WebhookEvent::COMMUNITY_POLL_OPENED)->toBe('community.poll.opened')
        ->and(WebhookEvent::COMMUNITY_POLL_CLOSED)->toBe('community.poll.closed')
        ->and(WebhookEvent::community())->toBe([
            'community.thread.status_changed',
            'community.post.created',
            'community.poll.opened',
            'community.poll.closed',
        ]);
});

it('names the ticket events and the delivery headers', function (): void {
    expect(WebhookEvent::ticket())->toBe([
        'ticket.created',
        'ticket.replied',
        'ticket.status_changed',
        'ticket.assigned',
        'ticket.sla_warning',
        'ticket.sla_breached',
    ])
        ->and(WebhookEvent::HEADER)->toBe('X-ChaosDesk-Event')
        ->and(WebhookEvent::DELIVERY_HEADER)->toBe('X-ChaosDesk-Delivery')
        ->and(WebhookEvent::SIGNATURE_HEADER)->toBe('X-ChaosDesk-Signature');
});

it('lists every event once', function (): void {
    expect(WebhookEvent::all())->toHaveCount(10)
        ->and(array_unique(WebhookEvent::all()))->toHaveCount(10);
});

it('tells a community event from a ticket event', function (): void {
    expect(WebhookEvent::isCommunity('community.poll.closed'))->toBeTrue()
        ->and(WebhookEvent::isCommunity('ticket.replied'))->toBeFalse()
        ->and(WebhookEvent::isCommunity('community.unknown'))->toBeFalse()
        ->and(WebhookEvent::isCommunity(null))->toBeFalse();
});

it('routes a signed community delivery in a host controller', function (): void {
    $body = (string) json_encode([
        'event' => WebhookEvent::COMMUNITY_POLL_CLOSED,
        'title' => 'Community poll closed: What should we build next?',
        'body' => 'What should we build next?',
        'url' => null,
        'color' => null,
        'metadata' => [
            'poll_ulid' => '01JPOLL0000000000000000000',
            'board_slug' => 'gurus',
            'question' => 'What should we build next?',
            'respondents_count' => 8,
            'results' => [
                ['option_ulid' => '01JOPTIONA0000000000000000', 'label' => 'CSV export', 'responses_count' => 6],
            ],
        ],
        'timestamp' => '2026-10-09T12:00:30+00:00',
    ]);

    $headers = [
        WebhookEvent::HEADER => WebhookEvent::COMMUNITY_POLL_CLOSED,
        WebhookEvent::SIGNATURE_HEADER => Signature::sign($body, 'whsec_test'),
    ];

    $handled = Signature::verify($body, $headers[WebhookEvent::SIGNATURE_HEADER], 'whsec_test')
        ? match ($headers[WebhookEvent::HEADER]) {
            WebhookEvent::COMMUNITY_POLL_CLOSED => json_decode($body, true)['metadata']['board_slug'],
            default => null,
        }
    : null;

    expect($handled)->toBe('gurus');
});
