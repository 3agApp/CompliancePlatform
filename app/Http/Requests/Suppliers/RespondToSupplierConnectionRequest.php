<?php

namespace App\Http\Requests\Suppliers;

use App\Rules\ValidSupplierConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RespondToSupplierConnectionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'connection' => ['required', new ValidSupplierConnection($this->user())],
        ];
    }

    /**
     * Get the validation data from the request.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'connection' => $this->route('connection'),
        ]);
    }
}
