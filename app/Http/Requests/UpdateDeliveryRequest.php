<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:admin checked via middleware
    }

    public function rules(): array
    {
        return [
            'status'         => ['sometimes', Rule::in(['pending', 'dispatched', 'delivered', 'failed'])],
            'scheduled_date' => ['sometimes', 'nullable', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
