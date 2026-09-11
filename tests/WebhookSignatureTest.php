<?php

declare(strict_types=1);

use ThreeOhEight\ChaosDesk\Webhooks\Signature;

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
