<?php

namespace App\Http\Requests\ProductCategories;

use App\Models\Organization;
use App\Models\ProductCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveProductCategoryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueWithinOrganization()],
        ];
    }

    /**
     * Reject a name the organization already uses, whatever its casing.
     *
     * The table's unique index is case sensitive, so "Toy" and "toy" would
     * both be accepted by the database and leave two families nobody can tell
     * apart on a product form. The comparison is written out here rather than
     * left to the unique rule, which can only match a name exactly.
     */
    protected function uniqueWithinOrganization(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $organization = $this->route('current_organization');

            if (! $organization instanceof Organization) {
                return;
            }

            $query = $organization->productCategories()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)]);

            $category = $this->route('product_category');

            if ($category instanceof ProductCategory) {
                $query->whereKeyNot($category->id);
            }

            if ($query->exists()) {
                $fail(__('You already have a category with this name.'));
            }
        };
    }

    /**
     * Get the custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('category name'),
        ];
    }
}
