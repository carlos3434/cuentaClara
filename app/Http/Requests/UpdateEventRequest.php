<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    /**
     * Admin (any event) or the owning organizer may edit — via the policy.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && $this->user() !== null
            && $this->user()->can('manage', $event);
    }

    /**
     * Role-conditional rules. Because validated() returns only ruled keys,
     * this block IS the server-side field whitelist: an organizer's crafted
     * name/slug/headcount/etc. is never validated and never applied.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');

        // Both roles: dates + amount. On edit the deadline may already be past,
        // so no `after_or_equal:today` (that guard lives in the create flow).
        $rules = [
            'event_date' => ['required', 'date'],
            'pay_deadline' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
        ];

        if ($this->user()->isAdmin()) {
            $rules += [
                'name' => ['required', 'string', 'max:120'],
                'headcount' => ['required', 'integer', 'min:1', 'max:1000'],
                'recipient_name' => ['required', 'string', 'max:120'],
                'recipient_handle' => ['nullable', 'string', 'max:60'],
                'accepted_methods' => ['required', 'array', 'min:1'],
                'accepted_methods.*' => [Rule::in(PaymentMethod::selectableValues())],
                'slug' => [
                    'required', 'string', 'min:4', 'max:40', 'regex:/^[a-z0-9-]+$/',
                    Rule::unique('events', 'slug')->ignore($event->id),
                ],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accepted_methods.required' => 'Selecciona al menos un método de pago.',
            'slug.regex' => 'El enlace solo puede tener minúsculas, números y guiones.',
            'slug.unique' => 'Ese enlace ya está en uso, elige otro.',
            'slug.min' => 'El enlace es demasiado corto.',
        ];
    }
}
