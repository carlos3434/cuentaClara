<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /**
     * Open for now: organizer auth is the next vertical slice.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'event_date' => ['required', 'date'],
            // Amount entered in soles (PEN), e.g. "480" or "480.50".
            'total_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'headcount' => ['required', 'integer', 'min:1', 'max:1000'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'recipient_handle' => ['nullable', 'string', 'max:60'],
            'accepted_methods' => ['required', 'array', 'min:1'],
            'accepted_methods.*' => [Rule::in(['yape', 'plin', 'bank_transfer'])],
            'pay_deadline' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accepted_methods.required' => 'Selecciona al menos un método de pago.',
            'pay_deadline.after_or_equal' => 'La fecha límite no puede ser en el pasado.',
        ];
    }
}
