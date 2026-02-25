<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['confirmed', 'out_for_delivery', 'delivered', 'cancelled'])],
            'notes'  => ['nullable', 'string', 'max:1000'],
            'cancellation_reason' => [
                Rule::requiredIf(fn () => $this->input('status') === 'cancelled'),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
