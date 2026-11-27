<?php

namespace App\Http\Requests\Products;

use App\Enums\ProductDocumentType;
use App\Support\HeicImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        'pdf', 'png', 'jpg', 'jpeg', 'webp', 'heic', 'heif', 'doc', 'docx', 'xls', 'xlsx',
    ];

    /**
     * The kinds of file a product image may be.
     *
     * A product photo is shown in the public gallery, so it has to be a
     * picture the browser draws -- not a PDF or a Word file filed under the
     * wrong kind. A HEIC photo counts: it is filed as a JPEG.
     *
     * @var array<int, string>
     */
    public const array PRODUCT_IMAGE_KINDS = ['png', 'jpg', 'jpeg', 'webp', 'heic', 'heif'];

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
            'documents.*.title' => ['nullable', 'string', 'max:255'],
            'documents.*.file' => [
                'required',
                'file',
                'mimes:'.implode(',', self::ACCEPTED_KINDS),
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * A file filed as a product image must be a picture. Judged by its
     * contents, as the `mimes:` rule is, so a PDF renamed to `.png` is
     * still refused.
     *
     * A HEIC photo is only taken where the server can convert it: filed as
     * it is, it could be neither previewed nor watermarked.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $heic = app(HeicImage::class);

                foreach ((array) $this->input('documents', []) as $index => $row) {
                    $attribute = "documents.{$index}.file";
                    $file = $this->file($attribute);

                    if (! is_array($row) || ! $file instanceof UploadedFile || $validator->errors()->has($attribute)) {
                        continue;
                    }

                    $position = (int) $index + 1;

                    if (($row['type'] ?? null) === ProductDocumentType::ProductImage->value
                        && ! in_array($file->guessExtension(), self::PRODUCT_IMAGE_KINDS, true)) {
                        $validator->errors()->add($attribute, __('File :position must be a PNG, JPEG, WebP or HEIC image to be filed as a product image.', [
                            'position' => $position,
                        ]));

                        continue;
                    }

                    if ($heic->isHeic($file) && ! $heic->canConvert()) {
                        $validator->errors()->add($attribute, __('File :position is a HEIC photo, which cannot be converted here. Save it as a JPEG and upload it again.', [
                            'position' => $position,
                        ]));
                    }
                }
            },
        ];
    }

    /**
     * Get the documents that were submitted.
     *
     * Validation has already refused the request without them, so the caller
     * is handed files rather than maybes.
     *
     * @return array<int, array{type: string, title: string|null, file: UploadedFile}>
     */
    public function documents(): array
    {
        /** @var array<int, array{type: string, title?: string|null}> $rows */
        $rows = $this->validated('documents');

        return collect($rows)
            ->values()
            ->map(fn (array $row, int $index): array => [
                'type' => $row['type'],
                'title' => filled($row['title'] ?? null) ? trim((string) $row['title']) : null,
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
            'documents.*.title.max' => __('The title of file :position may not be longer than 255 characters.'),
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
