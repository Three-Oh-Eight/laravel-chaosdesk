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

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();

        $message = is_array($body)
            ? ($body['message'] ?? $body['error'] ?? 'The ChaosDesk API returned an error.')
            : 'The ChaosDesk API returned an error.';

        $exception = new self($message, $response->status());
        $exception->status = $response->status();
        $exception->errors = is_array($body) && isset($body['errors']) && is_array($body['errors'])
            ? $body['errors']
            : [];

        return $exception;
    }

    public static function missingToken(): self
    {
        return new self(
            'No ChaosDesk site token configured. Set CHAOSDESK_SITE_TOKEN in your environment.'
        );
    }

    /**
     * Whether the API rejected the request because the payload was invalid.
     */
    public function isValidationError(): bool
    {
        return $this->status === 422;
    }
}
