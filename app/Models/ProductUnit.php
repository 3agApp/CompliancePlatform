<?php

namespace App\Models;

use App\Enums\ProductUnitStatus;
use App\Support\UnitSerial;
use Carbon\CarbonImmutable;
use Database\Factories\ProductUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * One physical packet of a product, known by the serial on its label.
 *
 * @property int $id
 * @property int $product_id
 * @property int $label_batch_id
 * @property string $serial
 * @property CarbonImmutable|null $first_checked_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product $product
 * @property-read LabelBatch $batch
 * @property-read Collection<int, ProductUnitCheck> $checks
 */
#[Fillable(['serial', 'first_checked_at', 'revoked_at'])]
class ProductUnit extends Model
{
    /** @use HasFactory<ProductUnitFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_checked_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Get the product the packet is one of.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the print run the packet's label came off.
     *
     * @return BelongsTo<LabelBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LabelBatch::class, 'label_batch_id');
    }

    /**
     * Get every check of the serial, oldest first.
     *
     * @return HasMany<ProductUnitCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(ProductUnitCheck::class)->orderBy('id');
    }

    /**
     * Get the serial the way it is printed.
     */
    public function formattedSerial(): string
    {
        return UnitSerial::format($this->serial);
    }

    /**
     * Get the address the packet's QR code points at.
     */
    public function url(): string
    {
        return route('products.public.unit', [
            'product' => $this->product->uuid,
            'serial' => $this->formattedSerial(),
        ]);
    }

    /**
     * The most earlier checks a buyer is shown. Enough to see the pattern;
     * the total says how many there were in all.
     */
    public const int HISTORY_LENGTH = 20;

    /**
     * Check the serial: keep the check, and say what it found.
     *
     * Every check is kept, so the next person to check sees this one. What
     * the reader is told is read off the checks that came before theirs:
     * none means a new packet, only their own means they checked before,
     * and anybody else's means the packet or its label has been in other
     * hands. Done under a lock on the packet, so two people checking the
     * same serial at the same moment cannot both be told they were first.
     *
     * A withdrawn code is not checked at all: the answer is that it was
     * withdrawn, and there is nothing a history would add to that.
     *
     * @return array{status: ProductUnitStatus, checkedAt: string|null, earlierChecks: int, history: array<int, array{at: string, thisDevice: bool}>}
     */
    public function check(string $deviceHash): array
    {
        if ($this->revoked_at !== null) {
            return ['status' => ProductUnitStatus::Revoked, 'checkedAt' => null, 'earlierChecks' => 0, 'history' => []];
        }

        return DB::transaction(function () use ($deviceHash) {
            self::query()->whereKey($this->id)->lockForUpdate()->first();

            $earlier = $this->checks()->reorder('id', 'desc')->get();
            $check = $this->checks()->create(['device_hash' => $deviceHash]);

            self::query()->whereKey($this->id)->whereNull('first_checked_at')->update(['first_checked_at' => $check->created_at]);

            $status = match (true) {
                $earlier->isEmpty() => ProductUnitStatus::FirstCheck,
                $earlier->every(fn (ProductUnitCheck $past) => hash_equals($past->device_hash, $deviceHash)) => ProductUnitStatus::CheckedBefore,
                default => ProductUnitStatus::CheckedElsewhere,
            };

            return [
                'status' => $status,
                'checkedAt' => $check->created_at?->toIso8601String(),
                'earlierChecks' => $earlier->count(),
                'history' => $earlier
                    ->take(self::HISTORY_LENGTH)
                    ->map(fn (ProductUnitCheck $past) => [
                        'at' => (string) $past->created_at?->toIso8601String(),
                        'thisDevice' => hash_equals($past->device_hash, $deviceHash),
                    ])
                    ->values()
                    ->all(),
            ];
        });
    }
}
