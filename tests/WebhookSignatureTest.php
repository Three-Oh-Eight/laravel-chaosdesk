<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use ThreeOhEight\ChaosDesk\Webhooks\Signature;
use ThreeOhEight\ChaosDesk\Webhooks\WebhookEvent;

beforeEach(function (): void {
    $this->body = '{"event":"ticket.replied","metadata":{"ticket_ulid":"01JABCDEFGHIJKLMNOPQRSTUVW","is_admin_reply":true}}';
    $this->secret = 'whsec_test_secret';
    $this->digest = hash_hmac('sha256', $this->body, $this->secret);
});

it('signs the raw body in the header format chaosdesk sends', function (): void {
    $header = Signature::sign($this->body, $this->secret);

    expect($header)->toBe('sha256='.$this->digest)
        ->and($header)->toMatch('/^sha256=[0-9a-f]{64}$/');
});

it('verifies a valid signature', function (): void {
    $header = Signature::sign($this->body, $this->secret);

    expect(Signature::verify($this->body, $header, $this->secret))->toBeTrue();
});

it('accepts an upper-case digest and surrounding whitespace', function (): void {
    $header = ' sha256='.strtoupper($this->digest)." \n";

    expect(Signature::verify($this->body, $header, $this->secret))->toBeTrue();
});

it('rejects a signature made with another secret', function (): void {
    $header = Signature::sign($this->body, 'whsec_other');

    expect(Signature::verify($this->body, $header, $this->secret))->toBeFalse();
});

it('rejects a tampered body', function (): void {
    $header = Signature::sign($this->body, $this->secret);
    $tampered = str_replace('"is_admin_reply":true', '"is_admin_reply":false', $this->body);

    expect(Signature::verify($tampered, $header, $this->secret))->toBeFalse();
});

it('rejects a missing header', function (): void {
    expect(Signature::verify($this->body, null, $this->secret))->toBeFalse()
        ->and(Signature::verify($this->body, '', $this->secret))->toBeFalse();
});

it('rejects a malformed header', function (): void {
    $malformed = [
        'bare digest' => $this->digest,
        'wrong algorithm' => 'sha1='.$this->digest,
        'short digest' => 'sha256=abc123',
        'non-hex digest' => 'sha256='.str_repeat('z', 64),
        'trailing garbage' => 'sha256='.$this->digest.'x',
        'two signatures' => 'sha256='.$this->digest.',sha256='.$this->digest,
    ];

    foreach ($malformed as $case => $header) {
        expect(Signature::verify($this->body, $header, $this->secret))->toBeFalse($case);
    }
});

it('never verifies with an empty secret', function (): void {
    $header = Signature::sign($this->body, '');

    expect(Signature::verify($this->body, $header, ''))->toBeFalse();
});

it('signs the timestamp and the body together', function (): void {
    $header = Signature::sign($this->body, $this->secret, 1_790_000_000);

    expect($header)->toBe('sha256='.hash_hmac('sha256', '1790000000.'.$this->body, $this->secret));
});

it('verifies a fresh timestamped signature', function (): void {
    Date::setTestNow('2026-10-24 12:00:00');
    $now = Date::now()->getTimestamp();

    expect(Signature::verify($this->body, Signature::sign($this->body, $this->secret, $now), $this->secret, (string) $now))->toBeTrue()
        ->and(Signature::verify($this->body, Signature::sign($this->body, $this->secret, $now - 300), $this->secret, (string) ($now - 300)))->toBeTrue()
        ->and(Signature::verify($this->body, Signature::sign($this->body, $this->secret, $now + 300), $this->secret, ' '.($now + 300).' '))->toBeTrue();
});

it('rejects a timestamp outside the tolerance window', function (): void {
    Date::setTestNow('2026-10-24 12:00:00');
    $now = Date::now()->getTimestamp();

    foreach (['stale' => $now - 301, 'future' => $now + 301, 'epoch' => 0] as $case => $timestamp) {
        $header = Signature::sign($this->body, $this->secret, $timestamp);

        expect(Signature::verify($this->body, $header, $this->secret, (string) $timestamp))->toBeFalse($case);
    }
});

it('refuses a captured delivery replayed after the window', function (): void {
    Date::setTestNow('2026-10-24 12:00:00');
    $headers = Signature::headers($this->body, $this->secret);

    $verify = fn (): bool => Signature::verify(
        $this->body,
        $headers['X-ChaosDesk-Signature'],
        $this->secret,
        $headers['X-ChaosDesk-Timestamp'],
    );

    expect($verify())->toBeTrue();

    Date::setTestNow('2026-10-24 12:05:01');

    expect($verify())->toBeFalse();
});

it('reads the tolerance from config or the argument', function (): void {
    Date::setTestNow('2026-10-24 12:00:00');
    $timestamp = Date::now()->getTimestamp() - 120;
    $header = Signature::sign($this->body, $this->secret, $timestamp);

    config()->set('chaosdesk.webhooks.tolerance', 60);

    expect(Signature::verify($this->body, $header, $this->secret, (string) $timestamp))->toBeFalse()
        ->and(Signature::verify($this->body, $header, $this->secret, (string) $timestamp, 180))->toBeTrue();
});

it('rejects a timestamp that is not the signed one', function (): void {
    Date::setTestNow('2026-10-24 12:00:00');
    $now = Date::now()->getTimestamp();
    $header = Signature::sign($this->body, $this->secret, $now);

    expect(Signature::verify($this->body, $header, $this->secret, (string) ($now - 1)))->toBeFalse()
        ->and(Signature::verify($this->body, Signature::sign($this->body, $this->secret), $this->secret, (string) $now))->toBeFalse();
});

it('rejects a malformed timestamp', function (): void {
    Date::setTestNow('2026-10-24 12:00:00');
    $now = (string) Date::now()->getTimestamp();
    $header = Signature::sign($this->body, $this->secret, (int) $now);

    foreach (['-'.$now, $now.'.0', '1e9', 'abc', '+'.$now] as $timestamp) {
        expect(Signature::verify($this->body, $header, $this->secret, $timestamp))->toBeFalse($timestamp);
    }
});

it('still verifies a body-only signature without a timestamp header until timestamps are required', function (): void {
    $header = Signature::sign($this->body, $this->secret);

    expect(Signature::verify($this->body, $header, $this->secret, null))->toBeTrue()
        ->and(Signature::verify($this->body, $header, $this->secret, ''))->toBeTrue();

    config()->set('chaosdesk.webhooks.require_timestamp', true);

    expect(Signature::verify($this->body, $header, $this->secret))->toBeFalse();
});

it('refuses a timestamped signature downgraded into a body-only one', function (): void {
    Date::setTestNow('2026-10-24 12:00:00');
    $now = Date::now()->getTimestamp();
    $header = Signature::sign($this->body, $this->secret, $now);

    // The timestamp moved into the body and the timestamp header dropped:
    // the HMAC input is identical, so only the shape check stops it.
    expect(Signature::verify($now.'.'.$this->body, $header, $this->secret))->toBeFalse();
});

it('verifies a request from its raw body and both headers', function (): void {
    Date::setTestNow('2026-10-24 12:00:00');
    $headers = Signature::headers($this->body, $this->secret);

    $request = Request::create('/webhooks/chaosdesk', 'POST', server: [
        'HTTP_X_CHAOSDESK_SIGNATURE' => $headers[WebhookEvent::SIGNATURE_HEADER],
        'HTTP_X_CHAOSDESK_TIMESTAMP' => $headers[WebhookEvent::TIMESTAMP_HEADER],
        'CONTENT_TYPE' => 'application/json',
    ], content: $this->body);

    expect($headers[WebhookEvent::TIMESTAMP_HEADER])->toBe((string) Date::now()->getTimestamp())
        ->and(Signature::verifyRequest($request, $this->secret))->toBeTrue()
        ->and(Signature::verifyRequest($request, 'whsec_other'))->toBeFalse();

    Date::setTestNow('2026-10-24 13:00:00');

    expect(Signature::verifyRequest($request, $this->secret))->toBeFalse();
});
