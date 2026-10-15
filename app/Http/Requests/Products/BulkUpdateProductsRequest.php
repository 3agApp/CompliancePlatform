<?php

namespace App\Http\Requests\Products;

use App\Enums\CountryOfOrigin;
use App\Enums\SupplierConnectionStatus;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * One change made to many products at once.
 *
 * Only the fields that are sent are changed: a field left out of the
 * request is left as each product has it, which is what tells "leave it"
 * apart from "clear it". The rules are the single product's rules, held to
 * the distributor that owns the products -- which is the current
 * organization, since only a distributor is offered more than the active
 * flag and the country.
 */
class BulkUpdateProductsRequest extends FormRequest
{
    /**
     * The most products one request may change, which bounds the work a
     * single submission can ask for.
     */
    public const int MAX_PRODUCTS = 500;

    /**
     * Determine whether the user may make bulk changes at all.
     *
     * Each product is authorized on its own by the controller; this only
     * keeps somebody who cannot see the list from being answered with a
     * list of what is wrong with their payload.
     */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', [Product::class, $this->route('current_organization')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PRODUCTS],
            'ids.*' => ['integer', 'distinct'],
            'is_active' => ['sometimes', 'boolean'],
            'country_of_origin' => ['sometimes', 'nullable', Rule::enum(CountryOfOrigin::class)],
        ];

        $organization = $this->route('current_organization');

        /**
         * A supplier keeps no families, sheets or brands of its own, and
         * may not reassign a product, so for one these keys are never
         * registered and do not survive validated().
         */
        if (! $organization instanceof Organization || ! $organization->isDistributor()) {
            return $rules;
        }

        return [
            ...$rules,
            'supplier_connection_id' => [
                'sometimes',
                'required',
                Rule::exists('supplier_connections', 'id')
                    ->where('distributor_organization_id', $organization->id)
                    ->whereIn('status', SupplierConnectionStatus::assignableValues()),
            ],
            'product_category_id' => [
                'required_with:product_template_id',
                'integer',
                Rule::exists('product_categories', 'id')->where('organization_id', $organization->id),
            ],
            'product_template_id' => ['required_with:product_category_id', 'integer', $this->underTheChosenCategory()],
            'brand_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * A brand belongs to one supplier, so it can only be set across
     * products that answer to one: the supplier chosen in the same
     * submission, or the one every selected product already shares.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('brand_id') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $connectionId = $this->brandConnectionId();

                if ($connectionId === null) {
                    $validator->errors()->add('brand_id', __('Select products from one supplier to set a brand.'));

                    return;
                }

                $carried = Brand::query()
                    ->whereKey((int) $this->input('brand_id'))
                    ->where('supplier_connection_id', $connectionId)
                    ->exists();

                if (! $carried) {
                    $validator->errors()->add('brand_id', __('Select one of the brands this supplier carries.'));
                }
            },
        ];
    }

    /**
     * The supplier whose brands the products may carry, if there is one.
     */
    protected function brandConnectionId(): ?int
    {
        if ($this->filled('supplier_connection_id')) {
            return (int) $this->input('supplier_connection_id');
        }

        $organization = $this->route('current_organization');

        $connections = Product::query()
            ->where('organization_id', $organization instanceof Organization ? $organization->id : null)
            ->whereIn('id', array_map('intval', (array) $this->input('ids', [])))
            ->distinct()
            ->pluck('supplier_connection_id');

        return $connections->count() === 1 ? (int) $connections->first() : null;
    }

    /**
     * Require the chosen template to sit under the chosen category.
     */
    protected function underTheChosenCategory(): Exists
    {
        $categoryId = $this->input('product_category_id');

        return Rule::exists('product_templates', 'id')
            ->where('product_category_id', is_numeric($categoryId) ? (int) $categoryId : null);
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => __('Select at least one product.'),
            'product_category_id.exists' => __('Select one of the available categories.'),
            'product_template_id.required_with' => __('Select the template this product is held to.'),
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
            'brand_id' => __('brand'),
            'product_category_id' => __('category'),
            'product_template_id' => __('template'),
            'country_of_origin' => __('country of origin'),
            'supplier_connection_id' => __('supplier'),
        ];
    }
}
