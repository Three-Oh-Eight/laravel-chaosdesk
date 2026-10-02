<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * A community rule refused the request, with the machine-readable code.
 *
 * ChaosDesk answers community refusals with `{"error": "<code>", "message":
 * "<text>"}`. The code tells "accept the charter first" from "you are
 * blocked" without parsing the message, so a UI reacts on `errorCode` (or
 * the predicates) rather than on the HTTP status. The status predicates of
 * ChaosDeskException still apply: a 403 code is also isUnauthorised(), a
 * 404 code also isNotFound().
 *
 * Not-found and cross-board access share the 404 codes on purpose: a member
 * probing another board learns nothing about whether it exists.
 */
class CommunityException extends ChaosDeskException
{
    public const BOARD_NOT_FOUND = 'board_not_found';

    public const THREAD_NOT_FOUND = 'thread_not_found';

    public const POST_NOT_FOUND = 'post_not_found';

    public const POLL_NOT_FOUND = 'poll_not_found';

    public const MEMBER_BLOCKED = 'member_blocked';

    public const CHARTER_NOT_ACCEPTED = 'charter_not_accepted';

    public const CHARTER_VERSION_MISMATCH = 'charter_version_mismatch';

    public const THREAD_LOCKED = 'thread_locked';

    public const THREAD_NOT_VOTABLE = 'thread_not_votable';

    public const POLL_CLOSED = 'poll_closed';

    public const POLL_NOT_OPEN = 'poll_not_open';

    public const POLL_RESULTS_HIDDEN = 'poll_results_hidden';

    public const MEMBER_IDENTITY_CONFLICT = 'member_identity_conflict';

    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $status,
        array $errors = [],
    ) {
        parent::__construct($message, $status);

        $this->status = $status;
        $this->errors = $errors;
    }

    /**
     * The community exception for a failed response, or null when the body
     * carries no machine code (a bad token, a validation error, a 5xx).
     *
     * Any snake_case `error` counts as a code, so a code a newer ChaosDesk
     * adds still surfaces here; compare it against the constants.
     */
    public static function tryFromResponse(Response $response): ?self
    {
        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        $code = $body['error'] ?? null;

        if (! is_string($code) || preg_match('/^[a-z]+(_[a-z]+)+$/', $code) !== 1) {
            return null;
        }

        $message = is_string($body['message'] ?? null) && $body['message'] !== ''
            ? $body['message']
            : 'ChaosDesk refused the community request.';

        $errors = isset($body['errors']) && is_array($body['errors']) ? $body['errors'] : [];

        return new self($code, $message, $response->status(), $errors);
    }

    /**
     * Whether the machine code is one of the given codes.
     */
    public function is(string ...$codes): bool
    {
        return in_array($this->errorCode, $codes, true);
    }

    /**
     * Whether the member has to (re)read and accept the board charter first:
     * never accepted, or the version they were shown is no longer current.
     */
    public function requiresCharterAcceptance(): bool
    {
        return $this->is(self::CHARTER_NOT_ACCEPTED, self::CHARTER_VERSION_MISMATCH);
    }

    public function isMemberBlocked(): bool
    {
        return $this->is(self::MEMBER_BLOCKED);
    }

    public function isThreadLocked(): bool
    {
        return $this->is(self::THREAD_LOCKED);
    }

    /**
     * Whether a poll refused the answer or its results: closed, not yet open,
     * or results still hidden while it runs.
     */
    public function isPollUnavailable(): bool
    {
        return $this->is(self::POLL_CLOSED, self::POLL_NOT_OPEN, self::POLL_RESULTS_HIDDEN);
    }
}
