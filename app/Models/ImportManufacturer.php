<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ImportManufacturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manufacturer in the source that the distributor chose to import, with the
 * supplier and template its products go to when the distributor said.
 *
 * @property int $id
 * @property int $import_source_id
 * @property string $external_id
 * @property string $name
 * @property int|null $supplier_connection_id
 * @property int|null $product_template_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ImportSource $source
 * @property-read SupplierConnection|null $supplierConnection
 * @property-read ProductTemplate|null $template
 */
#[Fillable(['external_id', 'name', 'supplier_connection_id', 'product_template_id'])]
class ImportManufacturer extends Model
{
    /** @use HasFactory<ImportManufacturerFactory> */
    use HasFactory;

    /**
     * Get the import source the manufacturer was chosen under.
     *
     * @return BelongsTo<ImportSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'import_source_id');
    }

    /**
     * Get the supplier the manufacturer's products are filed against.
     *
     * @return BelongsTo<SupplierConnection, $this>
     */
    public function supplierConnection(): BelongsTo
    {
        return $this->belongsTo(SupplierConnection::class);
    }

    /**
     * Get the template the manufacturer's new products are filed under.
     *
     * @return BelongsTo<ProductTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }
}
