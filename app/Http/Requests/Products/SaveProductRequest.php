<?php

namespace App\Http\Requests\Products;

use App\Enums\CountryOfOrigin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ean' => ['nullable', 'string', 'regex:/^(\d{8}|\d{12,14})$/'],
            'country_of_origin' => ['nullable', Rule::enum(CountryOfOrigin::class)],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ean.regex' => __('The EAN/barcode must be 8, 12, 13, or 14 digits.'),
        ];
    }

    /**
     * Get the custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ean' => __('EAN/barcode'),
            'country_of_origin' => __('country of origin'),
        ];
    }
}
