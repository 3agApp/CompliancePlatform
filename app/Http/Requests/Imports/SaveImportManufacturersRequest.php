<?php

namespace App\Http\Requests\Imports;

use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveImportManufacturersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('manageProductImport', $this->organization());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The ids are checked for shape rather than against the source: the list
     * came from the source a moment ago, and asking it again to save a form
     * would make saving depend on the source being up.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->organization();

        return [
            'manufacturers' => ['present', 'array', 'max:500'],
            'manufacturers.*.external_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct'],
            'manufacturers.*.name' => ['required', 'string', 'max:255'],
            'manufacturers.*.supplier_connection_id' => [
                'nullable',
                'integer',
                Rule::in($organization->supplierConnections()
                    ->whereIn('status', SupplierConnectionStatus::assignableValues())
                    ->pluck('id')
                    ->all()),
            ],
            'manufacturers.*.product_template_id' => [
                'nullable',
                'integer',
                Rule::in($organization->productTemplates()->pluck('product_templates.id')->all()),
            ],
        ];
    }

    /**
     * Get the manufacturers as rows to store.
     *
     * @return list<array{external_id: string, name: string, supplier_connection_id: int|null, product_template_id: int|null}>
     */
    public function manufacturers(): array
    {
        return array_values(array_map(fn (array $row): array => [
            'external_id' => (string) $row['external_id'],
            'name' => (string) $row['name'],
            'supplier_connection_id' => isset($row['supplier_connection_id']) ? (int) $row['supplier_connection_id'] : null,
            'product_template_id' => isset($row['product_template_id']) ? (int) $row['product_template_id'] : null,
        ], $this->validated('manufacturers')));
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manufacturers.*.supplier_connection_id.in' => __('Choose one of your connected suppliers.'),
            'manufacturers.*.product_template_id.in' => __('Choose one of your own templates.'),
        ];
    }

    /**
     * Get the organization associated with the request.
     */
    private function organization(): Organization
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return $organization;
    }
}
