<?php

namespace App\Http\Requests\Products;

use App\Enums\CountryOfOrigin;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'ean' => ['nullable', 'string', 'regex:/^(\d{8}|\d{12,14})$/'],
            'country_of_origin' => ['nullable', Rule::enum(CountryOfOrigin::class)],
        ];

        $organization = $this->route('current_organization');

        /**
         * The supplier is only ever chosen by the distributor that owns the
         * product. For a supplier organization the key is never registered,
         * so it does not survive validated() and a supplier cannot reassign a
         * product no matter what they submit.
         *
         * The scoped exists rule is also the only place that can express
         * "this connection belongs to this distributor", which the schema
         * cannot say on its own.
         */
        if ($organization instanceof Organization && $organization->isDistributor()) {
            $rules['supplier_connection_id'] = [
                'required',
                Rule::exists('supplier_connections', 'id')
                    ->where('distributor_organization_id', $organization->id)
                    ->whereIn('status', SupplierConnectionStatus::assignableValues()),
            ];
        }

        return $rules;
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
            'supplier_connection_id.required' => __('Select the supplier responsible for this product.'),
            'supplier_connection_id.exists' => __('Select one of your connected suppliers.'),
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
            'supplier_connection_id' => __('supplier'),
        ];
    }
}
