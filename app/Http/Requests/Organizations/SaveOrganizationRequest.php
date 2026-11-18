<?php

namespace App\Http\Requests\Organizations;

use App\Enums\Locale;
use App\Enums\OrganizationType;
use App\Rules\OrganizationName;
use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOrganizationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The type is fixed when the organization is created. Both the scoped
     * route binding for products and the dashboard navigation are derived
     * from it, so an organization that changed sides would lose access to
     * its own products.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new OrganizationName],
            'type' => $this->isMethod('post')
                ? ['required', Rule::enum(OrganizationType::class)]
                : ['prohibited'],
            'locale' => ['sometimes', Rule::enum(Locale::class)],
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', Rule::in(Countries::codes())],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:255'],
            'vat_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'represented_by' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.prohibited' => __('An organization cannot change type once it has been created.'),
        ];
    }
}
