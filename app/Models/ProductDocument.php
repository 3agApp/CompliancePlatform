<?php

namespace App\Models;

use App\Enums\DocumentPreviewKind;
use App\Enums\ProductDocumentType;
use Carbon\CarbonImmutable;
use Database\Factories\ProductDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * A paper filed against a product: a test report, a declaration of
 * conformity, a manual, an image of the packaging.
 *
 * The row records where the file is and what it was called; the file itself
 * sits on the private disk and is only ever handed over through an
 * authorized download.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $uploaded_by
 * @property ProductDocumentType $type
 * @property string $name
 * @property string $path
 * @property string|null $source_url
 * @property string $mime_type
 * @property int $size
 * @property bool $is_public
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product $product
 * @property-read User|null $uploader
 * @property-read Collection<int, ProductDocumentAnalysis> $analyses
 * @property-read ProductDocumentAnalysis|null $latestAnalysis
 */
#[Fillable(['type', 'name', 'path', 'source_url', 'mime_type', 'size', 'uploaded_by'])]
class ProductDocument extends Model
{
    /** @use HasFactory<ProductDocumentFactory> */
    use HasFactory;

    /**
     * The disk the files are kept on.
     *
     * Private on purpose. A declaration of conformity names a manufacturer,
     * a test house and a product that may not be on sale yet, and none of
     * that may be readable by whoever guesses the URL.
     */
    public const string DISK = 'local';

    /**
     * The directory one product's files are kept in.
     */
    public static function directoryFor(Product $product): string
    {
        return "product-documents/{$product->id}";
    }

    /**
     * Give a new file its kind's visibility, and take the file with the row.
     *
     * The delete only fires when a document is deleted on its own. A product taken away
     * takes its rows with it through the foreign key, which no model event
     * ever sees, so the controller clears that directory itself.
     */
    protected static function booted(): void
    {
        /**
         * A file that is not told otherwise takes its kind's default, so
         * every way of filing one -- upload, import -- agrees on it.
         */
        static::creating(function (ProductDocument $document) {
            if (! array_key_exists('is_public', $document->getAttributes())) {
                $document->is_public = $document->type?->isPublicByDefault() ?? false;
            }
        });

        static::deleted(function (ProductDocument $document) {
            Storage::disk(self::DISK)->delete($document->path);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductDocumentType::class,
            'size' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Get how this file would be shown unopened, or null if it cannot be.
     */
    public function previewKind(): ?DocumentPreviewKind
    {
        return DocumentPreviewKind::forContentType($this->mime_type);
    }

    /**
     * Get the content type a preview of this file is served under.
     */
    public function previewContentType(): ?string
    {
        return DocumentPreviewKind::contentTypeFor($this->mime_type);
    }

    /**
     * Get the product the document is filed against.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who filed the document, while there still is one.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get every AI reading of this paper, newest first.
     *
     * @return HasMany<ProductDocumentAnalysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(ProductDocumentAnalysis::class)->latest('id');
    }

    /**
     * Get the most recent AI reading of this paper.
     *
     * @return HasOne<ProductDocumentAnalysis, $this>
     */
    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(ProductDocumentAnalysis::class)->latestOfMany();
    }
}
