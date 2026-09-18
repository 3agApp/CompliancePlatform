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
    protected const int MAX_KILOBYTES = 10240;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ProductDocumentType::class)],
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', self::ACCEPTED_KINDS),
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * Get the file that was submitted.
     *
     * Validation has already refused the request without one, so the caller
     * is handed a file rather than a maybe.
     */
    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('Choose what kind of document this is.'),
            'type.enum' => __('Choose one of the available kinds of document.'),
            'file.required' => __('Choose a file to upload.'),
            'file.mimes' => __('Upload a PDF, an image, or a Word or Excel file.'),
            'file.max' => __('The file must be no larger than 10 MB.'),
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
            'type' => __('document kind'),
            'file' => __('file'),
        ];
    }
}
