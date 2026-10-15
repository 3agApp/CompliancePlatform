<?php

namespace App\Http\Requests\Imports;

use App\Enums\ImportExistingProductAction;
use App\Enums\ImportMatchKey;
use App\Enums\ImportProvider;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveImportSourceRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(ImportProvider::class)],
            'api_url' => ['required', 'string', 'max:2048', 'url:http,https'],

            /**
             * Only required the first time. Left blank afterwards it means
             * "keep the key I already gave you".
             */
            'api_key' => [
                Rule::requiredIf(fn (): bool => ! $this->organization()->importSource()->exists()),
                'nullable',
                'string',
                'max:512',
                'regex:/^\\S+$/',
            ],

            'file_url_prefix' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'match_key' => ['required', Rule::enum(ImportMatchKey::class)],
            'on_existing' => ['required', Rule::enum(ImportExistingProductAction::class)],
            'default_product_template_id' => [
                'nullable',
                'integer',
                Rule::in($this->organization()->productTemplates()->pluck('product_templates.id')->all()),
            ],
            'use_ai_for_templates' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the key to store, or null to keep the one already stored.
     */
    public function apiKey(): ?string
    {
        $key = $this->validated('api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Get the attributes to store, apart from the key.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            'provider' => $this->validated('provider'),
            'api_url' => $this->validated('api_url'),
            'file_url_prefix' => $this->validated('file_url_prefix'),
            'match_key' => $this->validated('match_key'),
            'on_existing' => $this->validated('on_existing'),
            'default_product_template_id' => $this->validated('default_product_template_id'),
            'use_ai_for_templates' => $this->boolean('use_ai_for_templates'),
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
            'api_key.required' => __('Paste the API key for the source.'),
            'api_key.regex' => __('An API key does not contain spaces — check what was pasted.'),
            'default_product_template_id.in' => __('Choose one of your own templates.'),
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
            'provider' => __('source'),
            'api_url' => __('API URL'),
            'api_key' => __('API key'),
            'file_url_prefix' => __('file URL prefix'),
            'match_key' => __('match key'),
            'on_existing' => __('existing products'),
            'default_product_template_id' => __('default template'),
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
