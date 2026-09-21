<?php

namespace App\Http\Requests\Products;

use App\Data\ProductDocumentCandidate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The metadata of files somebody is about to upload.
 *
 * Names, types and sizes only. The files themselves are still in the
 * browser and stay there until the kinds have been settled.
 */
class SuggestProductDocumentKindsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The same check as filing a document, so this is never a way for
     * somebody who may only read a product to spend the organization's AI
     * credit. A member is refused here, before the setting is read and long
     * before a prompt is built.
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
        return [
            'files' => ['required', 'array', 'min:1', 'max:'.SaveProductDocumentRequest::MAX_FILES],
            'files.*.name' => ['required', 'string', 'max:255'],
            'files.*.mime_type' => ['required', 'string', 'max:255'],
            'files.*.size' => ['required', 'integer', 'min:1', 'max:'.(SaveProductDocumentRequest::MAX_KILOBYTES * 1024)],
        ];
    }

    /**
     * Get the files to ask about.
     *
     * @return array<int, ProductDocumentCandidate>
     */
    public function candidates(): array
    {
        /** @var array<int, array{name: string, mime_type: string, size: int}> $rows */
        $rows = $this->validated('files');

        return array_map(
            fn (array $row): ProductDocumentCandidate => ProductDocumentCandidate::fromArray($row),
            array_values($rows),
        );
    }
}
