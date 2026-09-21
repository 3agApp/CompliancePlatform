<?php

namespace App\Http\Requests\Brands;

use App\Enums\SupplierConnectionStatus;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\SupplierConnection;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class SaveBrandRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The connection is only named when a brand is created. Renaming one
     * never moves it to another trade: the products already carrying it
     * belong to the supplier it was named under, and moving the row would
     * silently move them too.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', $this->uniqueWithinTheTrade()],
        ];

        if (! $this->route('brand') instanceof Brand) {
            $rules['supplier_connection_id'] = ['required', 'integer', $this->reachableByTheViewer()];
        }

        return $rules;
    }

    /**
     * Require the connection to be one the current organization is party to.
     *
     * A distributor may name a maker under any trade it holds; a supplier
     * only under a live one of its own. The scoped exists rule is the only
     * place that can say so, because the schema cannot.
     */
    protected function reachableByTheViewer(): Exists
    {
        $organization = $this->route('current_organization');
        $organizationId = $organization instanceof Organization ? $organization->id : null;

        $rule = Rule::exists('supplier_connections', 'id');

        /**
         * A supplier reaches only a live trade of its own. A distributor
         * reaches any trade a product could be assigned to, which takes in
         * a supplier who has not accepted their invitation yet and leaves
         * out one they have revoked.
         */
        return $organization instanceof Organization && $organization->isSupplier()
            ? $rule->where('supplier_organization_id', $organizationId)
                ->where('status', SupplierConnectionStatus::Active->value)
            : $rule->where('distributor_organization_id', $organizationId)
                ->whereIn('status', SupplierConnectionStatus::assignableValues());
    }

    /**
     * Reject a name the trade already uses, whatever its casing.
     *
     * The table's unique index is case sensitive, so "Alpro" and "alpro"
     * would both be accepted and leave two brands nobody can tell apart on
     * a product form. The comparison is written out here rather than left
     * to the unique rule, which can only match a name exactly.
     *
     * Two different suppliers may of course both carry an "Alpro", which is
     * why this is scoped to the connection rather than to an organization.
     */
    protected function uniqueWithinTheTrade(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $connection = $this->connection();

            if (! $connection instanceof SupplierConnection) {
                return;
            }

            $query = $connection->brands()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)]);

            $brand = $this->route('brand');

            if ($brand instanceof Brand) {
                $query->whereKeyNot($brand->id);
            }

            if ($query->exists()) {
                $fail(__('This supplier already has a brand with this name.'));
            }
        };
    }

    /**
     * Get the trade the brand is, or is about to be, named under.
     */
    protected function connection(): ?SupplierConnection
    {
        $brand = $this->route('brand');

        if ($brand instanceof Brand) {
            return $brand->supplierConnection;
        }

        $connectionId = $this->input('supplier_connection_id');

        return is_numeric($connectionId)
            ? SupplierConnection::query()->find((int) $connectionId)
            : null;
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_connection_id.required' => __('Select the supplier this brand belongs to.'),
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
            'name' => __('brand name'),
            'supplier_connection_id' => __('supplier'),
        ];
    }
}
