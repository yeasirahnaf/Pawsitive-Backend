<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pet_id'     => ['required', 'uuid', 'exists:pets,id'],
            'session_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
