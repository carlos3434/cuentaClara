<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Ownership of the route's event is enforced in the controller.
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
        $maxKb = config('cuentaclara.receipts_max_kb');

        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', "max:{$maxKb}"],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Adjunta una foto del comprobante del gasto.',
            'image.mimes' => 'Sube una imagen (JPG, PNG, WEBP o HEIC).',
            'image.max' => 'La imagen es demasiado grande.',
        ];
    }
}
