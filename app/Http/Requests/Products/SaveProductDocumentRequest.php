<?php

namespace App\Http\Requests\Products;

use App\Enums\ProductDocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class SaveProductDocumentRequest extends FormRequest
{
    /**
     * The kinds of file a compliance document may be.
     *
     * Wide enough for what a test house or a manufacturer actually sends --
     * a scanned report, a photographed label, a manual still in Word -- and
     * no wider, so an archive or an executable is never taken for evidence.
     *
     * @var array<int, string>
     */
    protected const array ACCEPTED_KINDS = [
        'pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx',
    ];

    /**
     * The largest file that may be filed, in kilobytes.
     */
    public const int MAX_KILOBYTES = 10240;

    /**
     * The most files that may be filed in one go.
     *
     * A folder from a test house is a handful of papers, not a hundred, and
     * the whole batch has to fit inside PHP's post_max_size -- which, when
     * exceeded, throws away the request body and the CSRF token with it, so
     * an oversized batch would read as an expired session rather than as
     * anything to do with files.
     */
    public const int MAX_FILES = 20;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'documents.*.type' => ['required', Rule::enum(ProductDocumentType::class)],
            'documents.*.file' => [
                'required',
                'file',
                'mimes:'.implode(',', self::ACCEPTED_KINDS),
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * Get the documents that were submitted.
     *
     * Validation has already refused the request without them, so the caller
     * is handed files rather than maybes.
     *
     * @return array<int, array{type: string, file: UploadedFile}>
     */
    public function documents(): array
    {
        /** @var array<int, array{type: string}> $rows */
        $rows = $this->validated('documents');

        return collect($rows)
            ->values()
            ->map(fn (array $row, int $index): array => [
                'type' => $row['type'],
                'file' => $this->uploadedFileAt($index),
            ])
            ->all();
    }

    /**
     * Get the custom validation messages.
     *
     * Every message names the row it is about, because a batch of eight
     * files that comes back saying only "must be no larger than 10 MB" is a
     * message about nothing in particular.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documents.required' => __('Choose at least one file to upload.'),
            'documents.array' => __('Choose at least one file to upload.'),
            'documents.max' => __('Upload no more than :max files at a time.'),
            'documents.*.type.required' => __('Choose what kind of document file :position is.'),
            'documents.*.type.enum' => __('Choose one of the available kinds of document for file :position.'),
            'documents.*.file.required' => __('File :position is missing.'),
            'documents.*.file.file' => __('File :position is missing.'),
            'documents.*.file.mimes' => __('File :position must be a PDF, an image, or a Word or Excel file.'),
            'documents.*.file.max' => __('File :position must be no larger than 10 MB.'),
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
            'documents' => __('files'),
        ];
    }

    /**
     * Get the uploaded file for one row.
     */
    private function uploadedFileAt(int $index): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file("documents.{$index}.file");

        return $file;
    }
}
