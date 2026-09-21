<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\DeleteOrganization;
use App\Enums\AiProvider;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DeleteOrganizationRequest;
use App\Http\Requests\Organizations\SaveOrganizationRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the user's organizations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('organizations/index', [
            'organizations' => $user->toUserOrganizations(includeCurrent: true),
        ]);
    }

    /**
     * Store a newly created organization.
     */
    public function store(SaveOrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $organization = $createOrganization->handle(
            $request->user(),
            $request->validated('name'),
            OrganizationType::from($request->validated('type')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }

    /**
     * Show the organization edit page.
     */
    public function edit(Request $request, Organization $organization): Response
    {
        $user = $request->user();

        return Inertia::render('organizations/edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'type' => $organization->type->value,
                'type_label' => $organization->type->label(),
            ],
            'members' => $organization->members()->get()->map(function (User $member) {
                /** @var Membership $membership */
                $membership = $member->getRelation('pivot');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar' => $member->avatar ?? null,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ];
            }),
            'invitations' => $organization->invitations()
                ->whereNull('accepted_at')
                ->get()
                ->map(fn ($invitation) => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $user->toOrganizationPermissions($organization),
            'availableRoles' => OrganizationRole::assignable(),
            'aiProvider' => $this->toAiProviderArray($user, $organization),
            'availableAiProviders' => AiProvider::options(),
        ]);
    }

    /**
     * Describe the connected AI provider for the page.
     *
     * The key is not in here, and never will be. All the page is given is
     * which provider and model are set and the last four characters of the
     * key, which is enough to tell one key from another and nothing more.
     *
     * Null for anyone who may not manage the provider, so a member is not
     * told what the organization is spending on either.
     *
     * @return array{provider: string, provider_label: string, model: string, model_label: string, key_hint: string, updated_at: string|null}|null
     */
    private function toAiProviderArray(User $user, Organization $organization): ?array
    {
        if (! $user->hasOrganizationPermission($organization, OrganizationPermission::ManageAiProvider)) {
            return null;
        }

        $setting = $organization->aiSetting;

        if ($setting === null) {
            return null;
        }

        /**
         * A key that no longer decrypts -- APP_KEY was rotated without the
         * stored values being re-encrypted -- reads as no provider at all.
         * There is nothing useful to show and nothing useful to prompt with,
         * and saving a new key puts it right.
         */
        try {
            $hint = $setting->keyHint();
        } catch (DecryptException) {
            return null;
        }

        return [
            'provider' => $setting->provider->value,
            'provider_label' => $setting->provider->label(),
            'model' => $setting->model,
            'model_label' => $setting->provider->modelLabel($setting->model),
            'key_hint' => $hint,
            'updated_at' => $setting->updated_at?->toISOString(),
        ];
    }

    /**
     * Update the specified organization.
     */
    public function update(SaveOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('update', $organization);

        $organization = DB::transaction(function () use ($request, $organization) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $organization->update(['name' => $request->validated('name')]);

            return $organization;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization updated.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }

    /**
     * Switch the user's current organization.
     */
    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()->belongsToOrganization($organization), 403);

        $request->user()->switchOrganization($organization);

        return back();
    }

    /**
     * Leave the specified organization.
     */
    public function leave(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('leave', $organization);

        $user = $request->user();

        $organization->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentOrganization($organization)) {
            $user->switchToFallbackOrganization($organization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the organization ":name"', ['name' => $organization->name])]);

        return to_route($user->currentOrganization ? 'organizations.index' : 'onboarding');
    }

    /**
     * Delete the specified organization.
     */
    public function destroy(
        DeleteOrganizationRequest $request,
        Organization $organization,
        DeleteOrganization $deleteOrganization,
    ): RedirectResponse {
        $user = $request->user();

        $deleteOrganization->handle($organization, keeping: $user);

        if ($user->isCurrentOrganization($organization)) {
            $user->switchToFallbackOrganization($organization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization deleted.')]);

        return to_route($user->fresh()->currentOrganization ? 'organizations.index' : 'onboarding');
    }
}
