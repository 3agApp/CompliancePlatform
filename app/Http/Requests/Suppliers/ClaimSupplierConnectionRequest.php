<?php

namespace App\Http\Requests\Suppliers;

use App\Models\SupplierConnection;
use App\Rules\BindableSupplierOrganization;
use App\Rules\OrganizationName;
use App\Rules\ValidSupplierConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClaimSupplierConnectionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $connection = $this->route('connection');

        abort_if(! $connection instanceof SupplierConnection, 404);

        return [
            'connection' => ['required', new ValidSupplierConnection($this->user())],
            'mode' => ['required', Rule::in(['create', 'existing'])],
            'name' => ['required_if:mode,create', 'nullable', 'string', 'max:255', new OrganizationName],
            'organization' => [
                'required_if:mode,existing',
                'nullable',
                'string',
                new BindableSupplierOrganization($this->user(), $connection),
            ],
        ];
    }

    /**
     * Get the validation data from the request.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'connection' => $this->route('connection'),
        ]);
    }

    /**
     * Get the custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('company name'),
            'organization' => __('company'),
        ];
    }
}
