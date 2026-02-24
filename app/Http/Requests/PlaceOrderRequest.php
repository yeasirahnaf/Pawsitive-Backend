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
            // Cart reference
            'session_id'             => ['nullable', 'string', 'max:255'],

            // Delivery address
            'address_line'           => ['required', 'string', 'max:500'],
            'city'                   => ['nullable', 'string', 'max:100'],
            'area'                   => ['nullable', 'string', 'max:100'],

            // Delivery fee
            'delivery_fee'           => ['required', 'numeric', 'min:0'],

            // Payment — COD only for now
            'payment_method'         => ['required', Rule::in(['cod'])],

            // Notes
            'notes'                  => ['nullable', 'string', 'max:1000'],

            // Guest checkout fields (required when not authenticated)
            'guest_name'             => ['nullable', 'string', 'max:255'],
            'guest_email'            => ['nullable', 'email', 'max:255'],
            'guest_phone'            => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->user() && ! $this->filled('guest_email')) {
                $validator->errors()->add('guest_email', 'Guest email is required for guest checkout.');
            }
        });
    }
}
