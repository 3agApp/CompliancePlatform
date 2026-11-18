<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveOrganizationLogoRequest extends FormRequest
{
    /**
     * The largest logo that may be uploaded, in kilobytes.
     */
    public const int MAX_KILOBYTES = 2048;

    /**
     * Get the validation rules that apply to the request.
     *
     * Raster images only. An SVG can carry script, and the logo is served
     * on a public address to anyone.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:'.self::MAX_KILOBYTES,
                'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000',
            ],
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
            'logo.mimes' => __('The logo must be a PNG, JPG or WebP image.'),
            'logo.image' => __('The logo must be a PNG, JPG or WebP image.'),
            'logo.max' => __('The logo may not be larger than 2 MB.'),
            'logo.dimensions' => __('The logo must be between 64 and 4000 pixels on each side.'),
        ];
    }
}
