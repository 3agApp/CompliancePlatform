<?php

namespace App\Http\Requests\Suppliers;

use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Rules\UniqueSupplierConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The address may only be left empty by a supplier that never had one,
     * which is one the product import added: one that has an address keeps
     * one, because the claim link is checked against it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('current_organization');
        $connection = $this->route('supplier_connection');

        abort_if(! $organization instanceof Organization || ! $connection instanceof SupplierConnection, 404);

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_email' => [
                Rule::requiredIf($connection->contact_email !== null),
                'nullable',
                'string',
                'email',
                'max:255',
                new UniqueSupplierConnection($organization, ignore: $connection),
            ],
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
            'company_name' => __('company name'),
            'contact_email' => __('contact email address'),
        ];
    }
}
