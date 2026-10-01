<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReopenProductReviewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The reason is required. Taking back a sign-off changes what the public
     * seal says, and the history is the only place anybody can later find
     * out why a product that was verified stopped being so.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:'.RequestProductChangesRequest::MAX_NOTE_LENGTH],
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
            'note.required' => __('Say why the approval is being taken back, so the history explains it.'),
        ];
    }
}
