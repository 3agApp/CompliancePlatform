<?php

namespace App\Models;

use App\Enums\DocumentPreviewKind;
use App\Support\PublicImage;
use Carbon\CarbonImmutable;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A maker behind one trading relationship, such as tigerbox or Magna-Tiles.
 *
 * The brand is the supplier's to name: they know what they make, and the
 * distributor sees it on the products that supplier answers for. It hangs
 * off the connection rather than off the supplier organization, because a
 * supplier who has not claimed their invitation yet has none, and their
 * products still have to be able to name a brand.
 *
 * Two connections carrying the same maker keep a row each. Neither side can
 * rename or delete the other trade's, and what one of them calls it says
 * nothing about anybody else's catalog.
 *
 * @property int $id
 * @property int $supplier_connection_id
 * @property string $name
 * @property string|null $logo_path
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SupplierConnection $supplierConnection
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 */
#[Fillable(['name'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    /**
     * The directory brand logos are kept in.
     */
    public const string LOGO_DIRECTORY = 'brand-logos';

    /**
     * Take the logo with the brand.
     *
     * Only fires when a brand is deleted on its own. Brands taken away with
     * their connection go through the foreign key, which no model event
     * sees, so whoever deletes the connection clears these directories.
     */
    protected static function booted(): void
    {
        static::deleted(function (Brand $brand) {
            Storage::disk(ProductDocument::DISK)->deleteDirectory($brand->logoDirectory());
        });
    }

    /**
     * The directory this brand's logo is filed under.
     */
    public function logoDirectory(): string
    {
        return self::LOGO_DIRECTORY.'/'.$this->id;
    }

    /**
     * Serve the brand's logo as a picture.
     *
     * The content type is read off the stored file's extension and checked
     * against the preview allowlist, as the organization logo is.
     */
    public function logoResponse(bool $public = true): StreamedResponse
    {
        $path = $this->logo_path;

        abort_if($path === null, 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = DocumentPreviewKind::forContentType('image/'.$extension) === DocumentPreviewKind::Image
            ? DocumentPreviewKind::contentTypeFor('image/'.$extension)
            : null;

        abort_if($contentType === null, 404);

        return PublicImage::response(ProductDocument::DISK, $path, 'logo.'.$extension, $contentType, $public);
    }

    /**
     * Get the trade this brand is named under.
     *
     * @return BelongsTo<SupplierConnection, $this>
     */
    public function supplierConnection(): BelongsTo
    {
        return $this->belongsTo(SupplierConnection::class);
    }

    /**
     * Get the products carrying this brand.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the brand as an option for a product form.
     *
     * The connection comes with it, because a product may only carry a
     * brand of the supplier it is assigned to: the form narrows the list
     * to the supplier already chosen rather than asking the server again.
     *
     * @return array{id: int, label: string, supplier_connection_id: int}
     */
    public function toOption(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'supplier_connection_id' => $this->supplier_connection_id,
        ];
    }
}
