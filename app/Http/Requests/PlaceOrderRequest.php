<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:sanctum — only logged-in users reach here
    }

    public function rules(): array
    {
        return [
            // Delivery address
            'address_line'   => ['required', 'string', 'max:500'],
            'city'           => ['nullable', 'string', 'max:100'],
            'area'           => ['nullable', 'string', 'max:100'],

            // Delivery fee
            'delivery_fee'   => ['required', 'numeric', 'min:0'],

            // Payment — COD only for now
            'payment_method' => ['required', Rule::in(['cod'])],

            // Notes
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
