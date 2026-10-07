<?php

namespace App\Http\Requests\Organizations;

use App\Enums\AiProvider;
use App\Models\Organization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveOrganizationAiSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('manageAiProvider', $this->route('organization'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(AiProvider::class)],

            /**
             * Only required the first time. Left blank afterwards it means
             * "keep the key I already gave you", so changing the model does
             * not make someone fetch their key out of a password manager
             * again.
             */
            'api_key' => [
                Rule::requiredIf(fn (): bool => ! $this->organizationHasKey()),
                'nullable',
                'string',
                'min:20',
                'max:512',
                'regex:/^\\S+$/',
            ],

            'model' => ['required', 'string'],

            'allow_document_analysis' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * The model is checked here rather than with Rule::in so that a bad
     * provider fails on its own rule instead of throwing on the way to
     * reading the model list.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $provider = AiProvider::tryFrom((string) $this->input('provider'));

                if ($provider === null || $validator->errors()->has('model')) {
                    return;
                }

                if (! $provider->offersModel((string) $this->input('model'))) {
                    $validator->errors()->add('model', __('Choose one of the models this provider offers.'));
                }
            },
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
     * Determine whether the organization agrees to its documents being sent
     * to the provider to be read.
     *
     * Off unless it is said, so a form that never showed the choice can
     * never make it.
     */
    public function allowsDocumentAnalysis(): bool
    {
        return $this->boolean('allow_document_analysis');
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider.required' => __('Choose an AI provider.'),
            'provider.enum' => __('Choose one of the available AI providers.'),
            'api_key.required' => __('Paste the API key for the provider you chose.'),
            'api_key.min' => __('That does not look like an API key.'),
            'api_key.regex' => __('An API key does not contain spaces — check what was pasted.'),
            'model.required' => __('Choose a model.'),
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
            'provider' => __('AI provider'),
            'api_key' => __('API key'),
            'model' => __('model'),
        ];
    }

    /**
     * Determine whether the organization already has a key stored.
     */
    private function organizationHasKey(): bool
    {
        return $this->organization()->aiSetting()->exists();
    }

    /**
     * Get the organization associated with the request.
     */
    private function organization(): Organization
    {
        $organization = $this->route('organization');

        abort_if(! $organization instanceof Organization, 404);

        return $organization;
    }
}
