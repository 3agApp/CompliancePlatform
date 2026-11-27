<?php

namespace App\Http\Requests\Products;

use App\Enums\Locale;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A product's safety texts as they stand on the form, to be translated.
 *
 * The texts are sent rather than read off the product, so what is
 * translated is what the person is looking at, saved or not.
 */
class SuggestProductTranslationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The same check as saving the product, so this is never a way for
     * somebody who may only read a product to spend the organization's AI
     * credit.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('product'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'from' => ['required', Rule::enum(Locale::class)],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['distinct', Rule::enum(Locale::class)],
            'texts' => ['required', 'array'],
        ];

        foreach (Product::TRANSLATABLE_FIELDS as $field) {
            $rules["texts.{$field}"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * Get the language the texts are written in.
     */
    public function from(): Locale
    {
        return Locale::from($this->string('from')->toString());
    }

    /**
     * Get the languages to translate into.
     *
     * @return list<Locale>
     */
    public function targets(): array
    {
        return array_values(array_map(fn (string $code): Locale => Locale::from($code), $this->validated('to')));
    }

    /**
     * Get the texts to translate, keyed by field.
     *
     * @return array<string, string|null>
     */
    public function texts(): array
    {
        return array_intersect_key((array) $this->validated('texts'), array_flip(Product::TRANSLATABLE_FIELDS));
    }
}
