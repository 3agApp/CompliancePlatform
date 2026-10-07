<?php

namespace App\Actions\Organizations;

use App\Enums\Locale;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    /**
     * Create a new organization and add the user as owner.
     *
     * The organization starts out speaking whatever language its founder
     * is using, which is the best guess there is about everybody else who
     * will join it. It can be changed in its settings.
     */
    public function handle(User $user, string $name, OrganizationType $type = OrganizationType::Distributor): Organization
    {
        return DB::transaction(function () use ($user, $name, $type) {
            $organization = Organization::create([
                'name' => $name,
                'type' => $type,
                'locale' => Locale::tryFrom(App::getLocale()) ?? Locale::default(),
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
