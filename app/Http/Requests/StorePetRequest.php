<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'species'        => ['required', 'string', 'max:100'],
            'breed'          => ['nullable', 'string', 'max:100'],
            'age_months'     => ['required', 'integer', 'min:0', 'max:300'],
            'gender'         => ['required', Rule::in(['male', 'female'])],
            'size'           => ['nullable', Rule::in(['small', 'medium', 'large', 'extra_large'])],
            'color'          => ['nullable', 'string', 'max:100'],
            'price'          => ['required', 'numeric', 'min:0', 'max:50000'],
            'health_records' => ['nullable', 'string'],
            'description'    => ['nullable', 'string'],
            'status'         => ['nullable', Rule::in(['available', 'reserved', 'sold'])],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
            'location_name'  => ['nullable', 'string', 'max:255'],
            'behaviours'     => ['nullable', 'array'],
            'behaviours.*'   => ['string', 'max:100'],
            'images'         => ['nullable', 'array', 'max:10'],
            'images.*'       => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }
}
