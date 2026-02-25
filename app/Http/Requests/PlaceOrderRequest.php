<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_line'   => ['required', 'string', 'max:500'],
            'city'           => ['nullable', 'string', 'max:100'],
            'area'           => ['nullable', 'string', 'max:100'],
            'delivery_fee'   => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(['cod'])],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
