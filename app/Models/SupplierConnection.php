<?php

namespace App\Models;

use App\Enums\SupplierConnectionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SupplierConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The link between a distributor and one of its suppliers, which doubles as
 * the invitation that creates it.
 *
 * The supplier organization is unknown until someone claims the invitation, so
 * products are assigned to the connection rather than to the supplier. That
 * lets a distributor assign products to a supplier who has not signed up yet,
 * keeps acceptance to a single row, and makes it impossible for one
 * distributor's products to reach another distributor through a shared
 * supplier.
 *
 * @property int $id
 * @property string $code
 * @property int $distributor_organization_id
 * @property int|null $supplier_organization_id
 * @property string $company_name
 * @property string $contact_email
 * @property SupplierConnectionStatus $status
 * @property CarbonImmutable|null $invited_at
 * @property int $invited_by
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $distributorOrganization
 * @property-read Organization|null $supplierOrganization
 * @property-read User $inviter
 * @property-read Collection<int, Product> $products
 */
#[Fillable([
    'distributor_organization_id',
    'supplier_organization_id',
    'company_name',
    'contact_email',
    'status',
    'invited_at',
    'invited_by',
    'expires_at',
    'accepted_at',
])]
class SupplierConnection extends Model
{
    /** @use HasFactory<SupplierConnectionFactory> */
    use HasFactory;

    /**
     * The number of days a claim link stays usable.
     */
    public const int CLAIM_EXPIRY_DAYS = 14;

    /**
     * How many days without a change before a supplier with drafts counts
     * as having gone quiet. One number, so every page agrees on who it is.
     */
    public const int QUIET_AFTER_DAYS = 14;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SupplierConnection $connection) {
            if (empty($connection->code)) {
                $connection->code = Str::random(64);
            }
        });
    }

    /**
     * Get the distributor that created the connection.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function distributorOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'distributor_organization_id');
    }

    /**
     * Get the supplier that claimed the connection, if it has been claimed.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function supplierOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'supplier_organization_id');
    }

    /**
     * Get the user who invited the supplier.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get the makers named under this trade.
     *
     * Ordered by name, because the list is only ever read as one: a
     * connection holds a handful of these and they are chosen from a
     * select on the product form.
     *
     * @return HasMany<Brand, $this>
     */
    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class)->orderBy('name');
    }

    /**
     * Get the products assigned to this connection.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Re-open an unclaimed connection with a fresh claim token.
     *
     * The previous link stops working, which is what makes a resend safe
     * after the original invitation went to the wrong person. Written with
     * forceFill because the claim token is deliberately not mass assignable.
     */
    public function reinvite(string $companyName): void
    {
        $this->forceFill([
            'code' => Str::random(64),
            'company_name' => $companyName,
            'status' => SupplierConnectionStatus::Pending,
            'invited_at' => now(),
            'expires_at' => now()->addDays(self::CLAIM_EXPIRY_DAYS),
            'accepted_at' => null,
        ])->save();
    }

    /**
     * Re-open an unclaimed connection without telling anybody yet.
     *
     * The same row comes back, so the products and brands already filed
     * under it stay put, but with no invitation outstanding: the old claim
     * link is replaced, and nothing is mailed until the distributor asks.
     */
    public function reopenUninvited(string $companyName): void
    {
        $this->forceFill([
            'code' => Str::random(64),
            'company_name' => $companyName,
            'status' => SupplierConnectionStatus::Pending,
            'invited_at' => null,
            'expires_at' => null,
            'accepted_at' => null,
        ])->save();
    }

    /**
     * Scope the query to connections a product may be assigned to.
     *
     * @param  Builder<SupplierConnection>  $query
     */
    public function scopeAssignable(Builder $query): void
    {
        $query->whereIn('status', SupplierConnectionStatus::assignableValues());
    }

    /**
     * Scope the query to connections whose claim link still works.
     *
     * @param  Builder<SupplierConnection>  $query
     */
    public function scopeClaimable(Builder $query): void
    {
        $query
            ->where('status', SupplierConnectionStatus::Pending)
            /**
             * A supplier added but not invited has never been sent the
             * link, so nobody signing in with that address should find an
             * invitation waiting for them.
             */
            ->whereNotNull('invited_at')
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()));
    }

    /**
     * Scope the query to connections still open to the given email address.
     *
     * @param  Builder<SupplierConnection>  $query
     */
    public function scopePendingFor(Builder $query, string $email): void
    {
        $query
            ->claimable()
            ->whereRaw('LOWER(contact_email) = ?', [strtolower($email)]);
    }

    /**
     * Determine if the connection is live.
     */
    public function isActive(): bool
    {
        return $this->status === SupplierConnectionStatus::Active;
    }

    /**
     * Determine if the connection is waiting to be claimed.
     */
    public function isPending(): bool
    {
        return $this->status === SupplierConnectionStatus::Pending;
    }

    /**
     * Determine if the invitation can still be claimed.
     */
    public function isClaimable(): bool
    {
        return $this->isPending() && $this->isInvited() && ! $this->isExpired();
    }

    /**
     * Determine if the claim link has been mailed.
     */
    public function isInvited(): bool
    {
        return $this->invited_at !== null;
    }

    /**
     * Determine if the invitation has expired.
     *
     * Expiry ends the claim link only. The row itself is never deleted,
     * because products reference it.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Name where the relationship stands, the way the distributor thinks of
     * it: a pending connection is told apart by whether the invitation has
     * gone out, and whether it has run out since.
     */
    public function statusLabel(): string
    {
        return match (true) {
            $this->isPending() && ! $this->isInvited() => __('Not invited'),
            $this->isPending() && $this->isExpired() => __('Invitation expired'),
            $this->isPending() => __('Invitation pending'),
            default => $this->status->label(),
        };
    }

    /**
     * Determine if a supplier organization has claimed the connection.
     */
    public function isClaimed(): bool
    {
        return $this->supplier_organization_id !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierConnectionStatus::class,
            'invited_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
