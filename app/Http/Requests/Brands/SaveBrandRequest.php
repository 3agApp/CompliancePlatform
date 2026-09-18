<?php

namespace App\Http\Requests\Brands;

use App\Models\Brand;
use App\Models\Organization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveBrandRequest extends FormRequest
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
     * The table's unique index is case sensitive, so "Alpro" and "alpro"
     * would both be accepted and leave two brands nobody can tell apart on a
     * product form. The comparison is written out here rather than left to
     * the unique rule, which can only match a name exactly.
     */
    protected function uniqueWithinOrganization(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $organization = $this->route('current_organization');

            if (! $organization instanceof Organization) {
                return;
            }

            $query = $organization->brands()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)]);

            $brand = $this->route('brand');

            if ($brand instanceof Brand) {
                $query->whereKeyNot($brand->id);
            }

            if ($query->exists()) {
                $fail(__('You already have a brand with this name.'));
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
            'name' => __('brand name'),
        ];
    }
}
