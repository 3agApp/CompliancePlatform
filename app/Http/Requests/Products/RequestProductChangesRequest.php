<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequestProductChangesRequest extends FormRequest
{
    /**
     * The longest note a reviewer may leave.
     *
     * Long enough for a list of everything still outstanding on a product,
     * short enough that the note stays a note: anything longer belongs in a
     * document filed against the product.
     */
    public const int MAX_NOTE_LENGTH = 2000;

    /**
     * Get the validation rules that apply to the request.
     *
     * The note is required, because sending a product back without saying
     * why leaves the supplier with nothing to act on -- which is the whole
     * difference between this and simply not approving it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:'.self::MAX_NOTE_LENGTH],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('Say what still needs to change, so the supplier knows what to do next.'),
        ];
    }
}
