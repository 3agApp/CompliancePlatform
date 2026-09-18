<?php

namespace App\Http\Requests\Products;

use App\Enums\CountryOfOrigin;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class SaveProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'integer', $this->categoryExistsRule()],
            'ean' => ['nullable', 'string', 'regex:/^(\d{8}|\d{12,14})$/'],
            'internal_article_number' => ['nullable', 'string', 'max:255'],
            'supplier_article_number' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'country_of_origin' => ['nullable', Rule::enum(CountryOfOrigin::class)],
        ];

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
     * Require the category to come from the list of the organization that
     * owns the product.
     *
     * The owner is the distributor, which is the current organization when a
     * product is being created and the product's own organization when a
     * supplier is filling in the details of one assigned to them. Without the
     * scope, any id from any organization's list would be accepted, and a
     * product would end up carrying a legal family its owner has never heard
     * of.
     */
    protected function categoryExistsRule(): Exists
    {
        $product = $this->route('product');

        $owner = $product instanceof Product
            ? $product->organization
            : $this->route('current_organization');

        return Rule::exists('product_categories', 'id')
            ->where('organization_id', $owner instanceof Organization ? $owner->id : null);
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
            'product_category_id.exists' => __('Select one of the available categories.'),
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
            'product_category_id' => __('category'),
            'internal_article_number' => __('internal article number'),
            'supplier_article_number' => __('supplier article number'),
            'order_number' => __('order number'),
            'country_of_origin' => __('country of origin'),
            'supplier_connection_id' => __('supplier'),
        ];
    }
}
