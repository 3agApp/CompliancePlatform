<?php

namespace App\Actions\Suppliers;

use App\Actions\Organizations\CreateOrganization;
use App\Enums\OrganizationType;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClaimSupplierConnection
{
    public function __construct(protected CreateOrganization $createOrganization)
    {
        //
    }

    /**
     * Bind a supplier organization to the connection and activate it.
     *
     * Passing an organization connects a company the user already runs;
     * passing a name creates a new supplier company with them as its owner.
     * Either way no product rows move: they were assigned to the connection,
     * which is what just gained a supplier.
     */
    public function handle(User $user, SupplierConnection $connection, ?Organization $organization, ?string $name): SupplierConnection
    {
        return DB::transaction(function () use ($user, $connection, $organization, $name) {
            /**
             * Re-read under a lock so two claims of the same invitation
             * cannot both pass the claimable check.
             */
            $connection = SupplierConnection::whereKey($connection->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($connection->isClaimable(), 409);

            $supplier = $organization ?? $this->createOrganization->handle(
                $user,
                (string) $name,
                OrganizationType::Supplier,
            );

            $connection->update([
                'supplier_organization_id' => $supplier->id,
                'status' => SupplierConnectionStatus::Active,
                'accepted_at' => now(),
            ]);

            $user->switchOrganization($supplier);

            return $connection;
        });
    }
}
