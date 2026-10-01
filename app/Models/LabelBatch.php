<?php

namespace App\Models;

use App\Enums\ProductEventType;
use App\Support\UnitSerial;
use Carbon\CarbonImmutable;
use Database\Factories\LabelBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One print run of serialised labels for a product.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int $product_id
 * @property int|null $created_by
 * @property int $quantity
 * @property string $issued_for
 * @property string|null $note
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Product $product
 * @property-read User|null $creator
 * @property-read Collection<int, ProductUnit> $units
 * @property-read Collection<int, ProductUnitCheck> $checks
 */
#[Fillable(['quantity', 'issued_for', 'note', 'revoked_at'])]
class LabelBatch extends Model
{
    /** @use HasFactory<LabelBatchFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (LabelBatch $batch) {
            if (empty($batch->uuid)) {
                $batch->uuid = (string) Str::uuid();
            }
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
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Issue a print run: one new serial for every packet.
     *
     * Written in one transaction, so a run is either all there or not there
     * at all -- half a roll of serials the platform does not know would
     * read as counterfeits to whoever bought those packets.
     */
    public static function issue(Product $product, int $quantity, string $issuedFor, ?string $note, User $actor, Organization $organization): self
    {
        return DB::transaction(function () use ($product, $quantity, $issuedFor, $note, $actor, $organization) {
            $batch = new self(['quantity' => $quantity, 'issued_for' => $issuedFor, 'note' => $note]);
            $batch->organization()->associate($product->organization_id);
            $batch->product()->associate($product);
            $batch->creator()->associate($actor);
            $batch->save();

            $now = now();

            $rows = array_map(fn (string $serial) => [
                'product_id' => $product->id,
                'label_batch_id' => $batch->id,
                'serial' => $serial,
                'created_at' => $now,
                'updated_at' => $now,
            ], self::freshSerials($quantity));

            foreach (array_chunk($rows, 250) as $chunk) {
                ProductUnit::query()->insert($chunk);
            }

            $product->recordEvent(
                ProductEventType::LabelsIssued,
                $actor,
                $organization,
                note: trans_choice(':count serialised label for :for|:count serialised labels for :for', $quantity, ['for' => $issuedFor]),
            );

            return $batch;
        });
    }

    /**
     * Draw serials nobody holds yet.
     *
     * Sixty bits make a collision all but impossible, but "all but" is not a
     * promise that may be printed on a box, so any serial already taken is
     * drawn again.
     *
     * @return list<string>
     */
    protected static function freshSerials(int $quantity): array
    {
        $serials = [];

        while (count($serials) < $quantity) {
            $candidates = [];

            while (count($serials) + count($candidates) < $quantity) {
                $candidates[UnitSerial::generate()] = true;
            }

            $taken = ProductUnit::query()->whereIn('serial', array_keys($candidates))->pluck('serial')->all();

            foreach (array_keys($candidates) as $serial) {
                if (! in_array($serial, $taken, true) && ! isset($serials[$serial])) {
                    $serials[$serial] = true;
                }
            }
        }

        return array_map('strval', array_keys($serials));
    }

    /**
     * Withdraw every label in the run that is still out there.
     */
    public function revoke(User $actor, Organization $organization): void
    {
        DB::transaction(function () use ($actor, $organization) {
            $now = now();

            $this->forceFill(['revoked_at' => $now])->save();
            $this->units()->whereNull('revoked_at')->update(['revoked_at' => $now, 'updated_at' => $now]);

            $this->product->recordEvent(
                ProductEventType::LabelsRevoked,
                $actor,
                $organization,
                note: trans_choice(':count serialised label for :for|:count serialised labels for :for', $this->quantity, ['for' => $this->issued_for]),
            );
        });
    }

    /**
     * Get the distributor the run was printed for.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the product the run is for.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get whoever asked for the run, while their account still exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the packets in the run, in the order they were printed.
     *
     * @return HasMany<ProductUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('id');
    }

    /**
     * Get every check of every packet in the run.
     *
     * @return HasManyThrough<ProductUnitCheck, ProductUnit, $this>
     */
    public function checks(): HasManyThrough
    {
        return $this->hasManyThrough(ProductUnitCheck::class, ProductUnit::class);
    }
}
