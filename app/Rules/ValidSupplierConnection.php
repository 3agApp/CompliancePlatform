<?php

namespace App\Rules;

use App\Models\SupplierConnection;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidSupplierConnection implements ValidationRule
{
    public function __construct(protected ?User $user)
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
        if (! $value instanceof SupplierConnection || ! $this->user instanceof User) {
            $fail(__('This invitation was sent to a different email address.'));

            return;
        }

        if ($value->isClaimed()) {
            $fail(__('This invitation has already been accepted.'));

            return;
        }

        if ($value->isExpired()) {
            $fail(__('This invitation has expired.'));

            return;
        }

        /**
         * A supplier the distributor has added but not invited was never
         * sent the link, so it reads the same as one withdrawn.
         */
        if (! $value->isPending() || ! $value->isInvited()) {
            $fail(__('This invitation is no longer available.'));

            return;
        }

        if (strtolower($value->contact_email) !== strtolower($this->user->email)) {
            $fail(__('This invitation was sent to a different email address.'));
        }
    }
}
