<?php

namespace App\Http\Controllers\Suppliers;

use App\Actions\Suppliers\ClaimSupplierConnection;
use App\Enums\OrganizationPermission;
use App\Enums\SupplierConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\ClaimSupplierConnectionRequest;
use App\Http\Requests\Suppliers\RespondToSupplierConnectionRequest;
use App\Models\Organization;
use App\Models\SupplierConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where a supplier claims a distributor's invitation.
 *
 * Claiming either creates their supplier company or connects one they already
 * run. That second option is how the same real company invited by two
 * distributors stays one company: the person claiming knows which it is.
 */
class SupplierConnectionClaimController extends Controller
{
    /**
     * Show the claim page.
     */
    public function show(Request $request, SupplierConnection $connection): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $connection->isClaimable() || strtolower($connection->contact_email) !== strtolower($user->email)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This invitation is no longer available.')]);

            return to_route('invitations.index');
        }

        $connection->load(['distributorOrganization', 'inviter']);

        return Inertia::render('connections/show', [
            'connection' => [
                'code' => $connection->code,
                'companyName' => $connection->company_name,
                'distributorName' => $connection->distributorOrganization->name,
                'inviterName' => $connection->inviter->name,
                'expiresAt' => $connection->expires_at?->toIso8601String(),
            ],
            'supplierOrganizations' => $this->bindableOrganizations($request, $connection),
        ]);
    }

    /**
     * Claim the invitation.
     */
    public function store(ClaimSupplierConnectionRequest $request, SupplierConnection $connection, ClaimSupplierConnection $claim): RedirectResponse
    {
        $organization = $request->validated('mode') === 'existing'
            ? Organization::where('slug', $request->validated('organization'))->firstOrFail()
            : null;

        $claim->handle(
            $request->user(),
            $connection,
            $organization,
            $request->validated('name'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection accepted.')]);

        return to_route('dashboard');
    }

    /**
     * Decline the invitation.
     *
     * The row stays unclaimed so the distributor can invite this contact
     * again later without creating a second connection.
     */
    public function destroy(RespondToSupplierConnectionRequest $request, SupplierConnection $connection): RedirectResponse
    {
        $connection->update(['status' => SupplierConnectionStatus::Declined]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        $user = $request->user();

        return to_route($user->currentOrganization ? 'dashboard' : 'onboarding');
    }

    /**
     * Get the supplier companies the user could connect to this distributor.
     *
     * @return array<array{name: string, slug: string}>
     */
    protected function bindableOrganizations(Request $request, SupplierConnection $connection): array
    {
        $connectedIds = SupplierConnection::query()
            ->where('distributor_organization_id', $connection->distributor_organization_id)
            ->whereNotNull('supplier_organization_id')
            ->pluck('supplier_organization_id');

        return $request->user()->organizations()
            ->get()
            ->filter(fn (Organization $organization) => $organization->isSupplier()
                && ! $connectedIds->contains($organization->id)
                && $request->user()->hasOrganizationPermission($organization, OrganizationPermission::UpdateOrganization))
            ->map(fn (Organization $organization) => [
                'name' => $organization->name,
                'slug' => $organization->slug,
            ])
            ->values()
            ->toArray();
    }
}
