<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a ticket submitted by one of your own clients.
 *
 * Authentication is the host application's business: these routes sit behind
 * whatever middleware wrapped ChaosDesk::routes().
 */
class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
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

            'context' => ['nullable', 'array'],
            'context.source' => ['nullable', 'string', 'in:web,ios,android,macos,api'],
            'context.device' => ['nullable', 'array'],
            'context.page' => ['nullable', 'array'],
            'context.app' => ['nullable', 'array'],
            'context.console' => ['nullable', 'array', 'max:50'],
            'context.extra' => ['nullable', 'array'],
        ];
    }
}
