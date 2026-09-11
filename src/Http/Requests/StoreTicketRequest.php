<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a ticket submitted by one of your own clients.
 *
 * Authentication is the host application's business: these routes sit behind
 * whatever middleware wrapped ChaosDesk::routes(). The limits mirror what the
 * ChaosDesk ingest endpoint enforces, so a bad payload fails here with a
 * readable 422 instead of a bad gateway.
 */
class StoreTicketRequest extends FormRequest
{
    public const MAX_TAGS = 10;

    public const TAG_PATTERN = '/^[a-z0-9-]{1,32}$/';

    public const MAX_EXTRA_KEYS = 20;

    public const EXTRA_KEY_PATTERN = '/^[A-Za-z0-9_.-]{1,64}$/';

    public const MAX_EXTRA_VALUE_LENGTH = 255;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|Closure>>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer'],
            'priority_id' => ['nullable', 'integer'],
            'custom_fields' => ['nullable', 'array'],
            'tags' => ['nullable', 'array', 'max:'.self::MAX_TAGS],
            'tags.*' => ['string', 'regex:'.self::TAG_PATTERN, 'distinct'],

            'context' => ['nullable', 'array'],
            'context.source' => ['nullable', 'string', 'in:web,ios,android,macos,api'],
            'context.device' => ['nullable', 'array'],
            'context.page' => ['nullable', 'array'],
            'context.app' => ['nullable', 'array'],
            'context.app.version' => ['nullable', 'string', 'max:64'],
            'context.app.build' => ['nullable', 'string', 'max:64'],
            'context.console' => ['nullable', 'array', 'max:50'],
            'context.extra' => ['nullable', 'array', 'max:'.self::MAX_EXTRA_KEYS, $this->extraShape()],
        ];
    }

    /**
     * Extra is a flat map of short keys to scalar values.
     *
     * Laravel has no rule for the shape of array keys, so this closure checks
     * both the keys and the values in one pass.
     */
    protected function extraShape(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $key => $item) {
                if (! is_string($key) || preg_match(self::EXTRA_KEY_PATTERN, $key) !== 1) {
                    $fail("The {$attribute} field contains an invalid key.");

                    return;
                }

                if ($item !== null && ! is_scalar($item)) {
                    $fail("The {$attribute}.{$key} field must be a scalar value.");

                    return;
                }

                if (is_string($item) && mb_strlen($item) > self::MAX_EXTRA_VALUE_LENGTH) {
                    $fail("The {$attribute}.{$key} field must not be greater than ".self::MAX_EXTRA_VALUE_LENGTH.' characters.');

                    return;
                }
            }
        };
    }
}
