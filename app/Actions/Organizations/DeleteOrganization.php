<?php

namespace App\Actions\Organizations;

use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DeleteOrganization
{
    /**
     * Delete an organization and wind down what it was party to.
     *
     * @param  User|null  $keeping  a user to leave alone, because the caller
     *                              switches them somewhere else afterwards
     */
    public function handle(Organization $organization, ?User $keeping = null): void
    {
        DB::transaction(function () use ($organization, $keeping) {
            User::query()
                ->where('current_organization_id', $organization->id)
                ->when($keeping, fn (Builder $query, User $user) => $query->where('id', '!=', $user->id))
                ->each(fn (User $affectedUser) => $affectedUser->switchToFallbackOrganization($organization));

            $organization->invitations()->delete();
            $organization->memberships()->delete();

            /**
             * Organizations are soft deleted, so the cascade on the foreign
             * key never fires. Left alone, an active connection would keep a
             * supplier reading and writing a deleted distributor's products.
             */
            $organization->supplierConnections()->update(['status' => SupplierConnectionStatus::Revoked]);
            $organization->distributorConnections()->update(['status' => SupplierConnectionStatus::Revoked]);

            $organization->delete();
        });
    }
}
