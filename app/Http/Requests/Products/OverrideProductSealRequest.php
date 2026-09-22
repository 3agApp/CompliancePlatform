<?php

namespace App\Http\Requests\Products;

use App\Enums\ProductSealStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverrideProductSealRequest extends FormRequest
{
    /**
     * The longest reason that may be given for a hand-set seal.
     */
    public const int MAX_REASON_LENGTH = 500;

    /**
     * Get the validation rules that apply to the request.
     *
     * An absent seal clears the override and hands the product back to its
     * review, which is the only case that needs no reason: going back to
     * what the check says is not a claim about anything.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'seal' => ['nullable', Rule::enum(ProductSealStatus::class)],
            'reason' => ['required_with:seal', 'nullable', 'string', 'max:'.self::MAX_REASON_LENGTH],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_with' => __('Say why this seal is being set by hand. It is kept with the product.'),
        ];
    }

    /**
     * Get the seal the product should show, or null to hand it back to the
     * review.
     */
    public function seal(): ?ProductSealStatus
    {
        $seal = $this->validated('seal');

        return $seal === null ? null : ProductSealStatus::from($seal);
    }
}
