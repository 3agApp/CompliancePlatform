<?php

namespace App\Http\Requests\Products;

use App\Enums\CountryOfOrigin;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class SaveProductRequest extends FormRequest
{
    /**
     * Determine whether the user may save this product.
     *
     * The controller authorizes too, and this is not redundant: a form
     * request validates before the controller method runs, so without this
     * somebody who may not touch the product at all would be answered with
     * a list of what is wrong with their payload instead of with a refusal.
     * A supplier organization keeps no categories, so the required category
     * alone would turn every unauthorized write into a validation error.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        if ($product instanceof Product) {
            return Gate::allows('update', $product);
        }

        return Gate::allows('create', [Product::class, $this->route('current_organization')]);
    }

    /**
     * Prepare the input for validation.
     *
     * A customs tariff number is read and quoted in groups -- "9503.00.75",
     * "9503 00 75" -- so whichever way it was typed or pasted, the
     * separators come off before it is checked or stored. Otherwise the same
     * code sits in the column three ways and none of them match each other.
     */
    protected function prepareForValidation(): void
    {
        $tariffNumber = $this->input('customs_tariff_number');

        if (! is_string($tariffNumber)) {
            return;
        }

        $digits = (string) preg_replace('/[\s.\-]/', '', $tariffNumber);

        $this->merge(['customs_tariff_number' => $digits === '' ? null : $digits]);
    }

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
            'brand_id' => ['nullable', 'integer', $this->underTheChosenSupplier()],
            'product_category_id' => ['required', 'integer', $this->ownedByTheProductOwner('product_categories')],
            'product_template_id' => ['required', 'integer', $this->underTheChosenCategory()],
            'ean' => ['nullable', 'string', 'regex:/^(\d{8}|\d{12,14})$/'],
            'internal_article_number' => ['nullable', 'string', 'max:255'],
            'supplier_article_number' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'customs_tariff_number' => ['nullable', 'string', 'regex:/^\d{6,12}$/'],
            'country_of_origin' => ['nullable', Rule::enum(CountryOfOrigin::class)],
            'age_grading' => ['nullable', 'string', 'max:255'],
            'safety_notice' => ['nullable', 'string', 'max:5000'],
            'warning_text' => ['nullable', 'string', 'max:5000'],
            'material_information' => ['nullable', 'string', 'max:5000'],
            'usage_restrictions' => ['nullable', 'string', 'max:5000'],
            'safety_instructions' => ['nullable', 'string', 'max:5000'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
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
     * Require the chosen brand to be one the product's supplier names.
     *
     * A maker belongs to one trade, so a product may only carry a brand of
     * the supplier it is assigned to. Which supplier that is depends on who
     * is asking: a distributor chooses it in the same submission, while a
     * supplier cannot reassign a product at all and is held to the one it
     * already has.
     */
    protected function underTheChosenSupplier(): Exists
    {
        $organization = $this->route('current_organization');
        $product = $this->route('product');

        $connectionId = $organization instanceof Organization && $organization->isDistributor()
            ? $this->input('supplier_connection_id')
            : ($product instanceof Product ? $product->supplier_connection_id : null);

        return Rule::exists('brands', 'id')
            ->where('supplier_connection_id', is_numeric($connectionId) ? (int) $connectionId : null);
    }

    /**
     * Require the chosen template to sit under the chosen category.
     *
     * No foreign key can say this: the product points at a family and at a
     * sheet, and nothing in the schema ties the two together. Pinning the
     * template to the submitted category is also all the tenancy check it
     * needs -- the category has already been required to belong to the
     * product's owner, so a template under it belongs to them too.
     */
    protected function underTheChosenCategory(): Exists
    {
        $categoryId = $this->input('product_category_id');

        return Rule::exists('product_templates', 'id')
            ->where('product_category_id', is_numeric($categoryId) ? (int) $categoryId : null);
    }

    /**
     * Require the chosen row to come from a list the product's owner keeps.
     *
     * The owner is the distributor, which is the current organization when a
     * product is being created and the product's own organization when a
     * supplier is filling in the details of one assigned to them. Without the
     * scope, any id from any organization's list would be accepted, and a
     * product would end up carrying a brand or a legal family its owner has
     * never heard of.
     */
    protected function ownedByTheProductOwner(string $table): Exists
    {
        $product = $this->route('product');

        $owner = $product instanceof Product
            ? $product->organization
            : $this->route('current_organization');

        return Rule::exists($table, 'id')
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
            'customs_tariff_number.regex' => __('The customs tariff number must be 6 to 12 digits.'),
            'brand_id.exists' => __('Select one of the brands this supplier carries.'),
            'product_category_id.exists' => __('Select one of the available categories.'),
            'product_template_id.required' => __('Select the template this product is held to.'),
            'product_template_id.exists' => __('Select one of the templates under the chosen category.'),
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
            'brand_id' => __('brand'),
            'product_category_id' => __('category'),
            'product_template_id' => __('template'),
            'internal_article_number' => __('internal article number'),
            'supplier_article_number' => __('supplier article number'),
            'order_number' => __('order number'),
            'customs_tariff_number' => __('customs tariff number'),
            'country_of_origin' => __('country of origin'),
            'supplier_connection_id' => __('supplier'),
            'age_grading' => __('age grading'),
            'safety_notice' => __('safety notice'),
            'warning_text' => __('warning text'),
            'material_information' => __('material information'),
            'usage_restrictions' => __('usage restrictions'),
            'safety_instructions' => __('safety instructions'),
            'additional_notes' => __('additional notes'),
        ];
    }
}
