<?php

namespace App\Http\Requests\Organizations;

use App\Enums\Country;
use App\Models\Organization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveOrganizationProfileRequest extends FormRequest
{
    /**
     * The most countries a supplier may say it produces in.
     */
    public const int MAX_PRODUCTION_COUNTRIES = 30;

    /**
     * Prepare the data for validation.
     *
     * A website typed without its scheme -- "3ag.ch" -- is what people do,
     * so it is completed rather than refused.
     */
    protected function prepareForValidation(): void
    {
        $website = trim((string) $this->input('website', ''));

        if ($website !== '' && ! Str::startsWith(Str::lower($website), ['http://', 'https://'])) {
            $this->merge(['website' => 'https://'.$website]);
        }

        $mapUrl = trim((string) $this->input('map_url', ''));

        if ($mapUrl !== '' && ! Str::startsWith(Str::lower($mapUrl), ['http://', 'https://'])) {
            $this->merge(['map_url' => 'https://'.$mapUrl]);
        }

        if ($this->has('production_countries') && ! is_array($this->input('production_countries'))) {
            $this->merge(['production_countries' => []]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only a supplier makes things, so only a supplier may say where.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return [
            'street' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', Rule::enum(Country::class)],
            'map_url' => ['nullable', 'string', 'max:2048', 'url:https', $this->googleMapsLink()],
            'represented_by' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\/.\-\s]+$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'url:http,https', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'production_countries' => $organization->isSupplier()
                ? ['sometimes', 'array', 'max:'.self::MAX_PRODUCTION_COUNTRIES]
                : ['prohibited'],
            'production_countries.*' => ['distinct', Rule::enum(Country::class)],
        ];
    }

    /**
     * Require a link that opens Google Maps.
     *
     * Shared links come in a few shapes -- maps.app.goo.gl short links,
     * google.com/maps and maps.google.com addresses in any country domain --
     * and anything else is refused, so the public page never sends a reader
     * somewhere they did not expect.
     */
    protected function googleMapsLink(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            $path = (string) parse_url((string) $value, PHP_URL_PATH);

            $googleDomain = 'google\.(?:com|[a-z]{2}|com?\.[a-z]{2})';

            $isGoogleMaps = $host === 'maps.app.goo.gl'
                || ($host === 'goo.gl' && str_starts_with($path, '/maps'))
                || preg_match('/^maps\.'.$googleDomain.'$/', $host) === 1
                || (preg_match('/^(?:www\.)?'.$googleDomain.'$/', $host) === 1 && str_starts_with($path, '/maps'));

            if (! $isGoogleMaps) {
                $fail(__('Paste a link from Google Maps, such as https://maps.app.goo.gl/…'));
            }
        };
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Enter a phone number using digits, spaces and + ( ) - / only.'),
            'production_countries.prohibited' => __('Only a supplier can say which countries it produces in.'),
            'production_countries.max' => __('Choose at most :max countries.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'street' => __('street'),
            'address_line_2' => __('address line 2'),
            'postal_code' => __('postal code'),
            'city' => __('city'),
            'country' => __('country'),
            'map_url' => __('Google Maps link'),
            'represented_by' => __('represented by'),
            'phone' => __('phone'),
            'email' => __('email'),
            'website' => __('website'),
            'vat_number' => __('VAT number'),
            'production_countries.*' => __('country'),
        ];
    }
}
