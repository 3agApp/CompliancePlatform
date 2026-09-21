<?php

namespace App\Http\Requests\ProductTemplates;

use App\Enums\ProductRequirement;
use App\Models\ProductCategory;
use App\Models\ProductTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveProductTemplateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The requirements arrive as a flag per column rather than as a list,
     * which is also how they are stored: a box the editor did not send is a
     * box that is not ticked, so every one is filled in before the rules
     * run and an unchecked requirement clears rather than lingering.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', $this->uniqueWithinCategory()],
        ];

        foreach (ProductRequirement::columns() as $column) {
            $rules[$column] = ['boolean'];
        }

        return $rules;
    }

    /**
     * Prepare the input for validation.
     *
     * An HTML form sends nothing for a box that is not ticked. Defaulting
     * every requirement to false here is what lets an edit turn one off.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(
            collect(ProductRequirement::columns())
                ->mapWithKeys(fn (string $column) => [
                    $column => $this->boolean($column),
                ])
                ->all()
        );
    }

    /**
     * Require the name to be free within the family it is filed under.
     *
     * The composite unique on the table is case sensitive, so "Standard"
     * and "standard" would both be accepted by the database and read as two
     * sheets of the same name in a select. Two different families may of
     * course both have a "Standard", which is why this is scoped to the
     * category rather than to the organization.
     */
    protected function uniqueWithinCategory(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $category = $this->route('product_category');

            if (! $category instanceof ProductCategory) {
                return;
            }

            $query = $category->templates()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)]);

            $template = $this->route('template');

            if ($template instanceof ProductTemplate) {
                $query->whereKeyNot($template->id);
            }

            if ($query->exists()) {
                $fail(__('This category already has a template with this name.'));
            }
        };
    }

    /**
     * Get the custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => __('template name')];
    }
}
