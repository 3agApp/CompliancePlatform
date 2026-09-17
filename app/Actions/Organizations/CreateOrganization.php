<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    /**
     * Create a new organization and add the user as owner.
     */
    public function handle(User $user, string $name, OrganizationType $type = OrganizationType::Distributor): Organization
    {
        return DB::transaction(function () use ($user, $name, $type) {
            $organization = Organization::create([
                'name' => $name,
                'type' => $type,
            ]);

            $organization->memberships()->create([
                'user_id' => $user->id,
                'role' => OrganizationRole::Owner,
            ]);

            $user->switchOrganization($organization);

            return $organization;
        });
    }
}
