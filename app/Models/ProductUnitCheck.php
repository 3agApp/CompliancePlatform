<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One check of a serial.
 *
 * Append only, and written without an updated_at: nothing about a check
 * that happened ever changes.
 *
 * @property int $id
 * @property int $product_unit_id
 * @property string $device_hash
 * @property CarbonImmutable|null $created_at
 * @property-read ProductUnit $unit
 */
#[Fillable(['device_hash'])]
class ProductUnitCheck extends Model
{
    const UPDATED_AT = null;

    /**
     * Get the packet that was checked.
     *
     * @return BelongsTo<ProductUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }
}
