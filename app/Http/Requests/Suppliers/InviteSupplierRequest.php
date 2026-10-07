<?php

namespace App\Http\Requests\Suppliers;

use App\Models\Organization;
use App\Rules\UniqueSupplierConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InviteSupplierRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'email', 'max:255', new UniqueSupplierConnection($organization)],
            'send_invitation' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Determine if the claim link should be mailed now.
     *
     * Inviting is what the form has always done, so a request that does
     * not say otherwise still sends it.
     */
    public function sendsInvitation(): bool
    {
        return $this->boolean('send_invitation', true);
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
