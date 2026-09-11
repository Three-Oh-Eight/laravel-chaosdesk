<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class ChaosDeskException extends RuntimeException
{
    /**
     * Validation errors keyed by field, when the API rejected the payload.
     *
     * @var array<string, list<string>>
     */
    public array $errors = [];

    public ?int $status = null;

    /**
     * Set when ChaosDesk could not be reached at all (no HTTP status).
     */
    protected bool $unreachable = false;

    /**
     * Map a failed API response onto the matching exception.
     *
     * 401 and 403 are unauthorised, 404 is not found, 422 carries the
     * validation errors, 5xx means ChaosDesk is unavailable; anything else
     * becomes a generic exception with the status attached.
     */
    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $status = $response->status();

        $message = is_array($body)
            ? (string) ($body['message'] ?? $body['error'] ?? 'The ChaosDesk API returned an error.')
            : 'The ChaosDesk API returned an error.';

        $errors = is_array($body) && isset($body['errors']) && is_array($body['errors'])
            ? $body['errors']
            : [];

        return match (true) {
            $status === 401, $status === 403 => self::unauthorised($message, $status),
            $status === 404 => self::notFound($message),
            $status === 422 => self::validation($errors, $message),
            $status >= 500 => self::unavailable($message, $status),
            default => self::withStatus($message, $status),
        };
    }

    public static function unauthorised(string $message = 'ChaosDesk rejected the credentials.', int $status = 401): self
    {
        return self::withStatus($message, $status);
    }

    public static function notFound(string $message = 'ChaosDesk could not find that resource.'): self
    {
        return self::withStatus($message, 404);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function validation(array $errors, string $message = 'The given data was invalid.'): self
    {
        $exception = self::withStatus($message, 422);
        $exception->errors = $errors;

        return $exception;
    }

    /**
     * ChaosDesk answered with a server error, or could not be reached at all.
     */
    public static function unavailable(string $message = 'ChaosDesk is currently unavailable.', ?int $status = null): self
    {
        $exception = new self($message, $status ?? 0);
        $exception->status = $status;
        $exception->unreachable = $status === null;

        return $exception;
    }

    public static function missingToken(string $site = 'default'): self
    {
        if ($site === 'default') {
            return new self(
                'No ChaosDesk site token configured. Set CHAOSDESK_SITE_TOKEN in your environment.'
            );
        }

        return new self(
            "No ChaosDesk site token configured for site [{$site}]. Set chaosdesk.sites.{$site}.token."
        );
    }

    public static function missingAgentToken(string $site = 'default'): self
    {
        if ($site === 'default') {
            return new self(
                'No ChaosDesk agent token configured. Set CHAOSDESK_AGENT_TOKEN in your environment.'
            );
        }

        return new self(
            "No ChaosDesk agent token configured for site [{$site}]. Set chaosdesk.sites.{$site}.agent_token."
        );
    }

    /**
     * Whether the API rejected the request because the payload was invalid.
     */
    public function isValidationError(): bool
    {
        return $this->status === 422;
    }

    /**
     * Whether the credentials were rejected (401) or the token may not reach the resource (403).
     */
    public function isUnauthorised(): bool
    {
        return $this->status === 401 || $this->status === 403;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    /**
     * Whether ChaosDesk answered with a server error or could not be reached.
     */
    public function isUnavailable(): bool
    {
        return $this->unreachable || ($this->status !== null && $this->status >= 500);
    }

    private static function withStatus(string $message, int $status): self
    {
        $exception = new self($message, $status);
        $exception->status = $status;

        return $exception;
    }
}
