<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationType;
use App\Rules\OrganizationName;
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
