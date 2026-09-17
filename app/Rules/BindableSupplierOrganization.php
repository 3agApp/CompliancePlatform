<?php

namespace App\Rules;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates the organization a recipient picks when claiming an invitation
 * with a supplier company they already run.
 *
 * This is also where two distributors reaching the same real company get
 * reconciled: the person claiming the invitation is the one who knows which
 * of their companies it belongs to.
 */
class BindableSupplierOrganization implements ValidationRule
{
    public function __construct(
        protected ?User $user,
        protected SupplierConnection $connection,
    ) {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $organization = Organization::where('slug', $value)->first();

        if (! $this->user instanceof User || $organization === null) {
            $fail(__('Select one of your supplier companies.'));

            return;
        }

        if (! $this->user->hasOrganizationPermission($organization, OrganizationPermission::UpdateOrganization)) {
            $fail(__('You need to be an owner or admin of that company to connect it.'));

            return;
        }

        if (! $organization->isSupplier()) {
            $fail(__('Only a supplier company can be connected to a distributor.'));

            return;
        }

        $alreadyConnected = SupplierConnection::query()
            ->where('distributor_organization_id', $this->connection->distributor_organization_id)
            ->where('supplier_organization_id', $organization->id)
            ->whereKeyNot($this->connection->id)
            ->exists();

        if ($alreadyConnected) {
            $fail(__('This company is already connected to that distributor.'));
        }
    }
}
