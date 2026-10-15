<?php

namespace App\Rules;

use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\SupplierConnection;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Keeps a distributor from inviting the same contact twice.
 *
 * Declined and revoked connections deliberately pass: inviting that contact
 * again re-opens the existing row rather than creating a second one.
 */
class UniqueSupplierConnection implements ValidationRule
{
    /**
     * @param  SupplierConnection|null  $ignore  The connection being edited, which may keep its own address.
     */
    public function __construct(protected Organization $distributor, protected ?SupplierConnection $ignore = null)
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = $this->distributor->supplierConnections()
            ->whereRaw('LOWER(contact_email) = ?', [strtolower((string) $value)])
            ->whereIn('status', SupplierConnectionStatus::assignableValues())
            ->when($this->ignore, fn ($query, SupplierConnection $ignore) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($exists) {
            $fail(__('You already have a supplier connection with this email address.'));
        }
    }
}
