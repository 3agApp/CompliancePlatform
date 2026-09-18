<?php

namespace App\Http\Controllers\Suppliers;

use App\Enums\SupplierConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\InviteSupplierRequest;
use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Notifications\Suppliers\SupplierConnectionInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The distributor's side of the relationship: inviting suppliers and ending
 * or restoring those relationships.
 */
class SupplierConnectionController extends Controller
{
    /**
     * Display the distributor's suppliers.
     */
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [SupplierConnection::class, $currentOrganization]);

        return Inertia::render('suppliers/index', [
            'connections' => $currentOrganization->supplierConnections()
                ->with('supplierOrganization')
                ->withCount('products')
                ->orderBy('company_name')
                ->get()
                ->map(fn (SupplierConnection $connection) => $this->toConnectionArray($connection)),
            'permissions' => $request->user()->toSupplierConnectionPermissions($currentOrganization),
        ]);
    }

    /**
     * Invite a supplier, or re-open a connection that ended earlier.
     *
     * The contact email is never looked up against existing accounts, so a
     * distributor cannot learn who is already on the platform.
     */
    public function store(InviteSupplierRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [SupplierConnection::class, $currentOrganization]);

        $companyName = $request->validated('company_name');
        $contactEmail = $request->validated('contact_email');

        $connection = $currentOrganization->supplierConnections()
            ->whereRaw('LOWER(contact_email) = ?', [strtolower($contactEmail)])
            ->whereNull('supplier_organization_id')
            ->first();

        if ($connection instanceof SupplierConnection) {
            $connection->reinvite($companyName);
        } else {
            $connection = $currentOrganization->supplierConnections()->create([
                'company_name' => $companyName,
                'contact_email' => $contactEmail,
                'status' => SupplierConnectionStatus::Pending,
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(SupplierConnection::CLAIM_EXPIRY_DAYS),
            ]);
        }

        $this->sendInvitation($connection);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('suppliers.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Send the claim link again with a fresh token.
     *
     * A connection revoked before anybody claimed it is re-opened the same
     * way, because that is what a distributor means by inviting them again.
     * Re-typing the address into the invite form already does exactly this,
     * so the button only makes an existing path discoverable. A declined
     * invitation is left out: the recipient said no, and one step of friction
     * before asking again is the right amount.
     */
    public function resend(Organization $currentOrganization, SupplierConnection $supplierConnection): RedirectResponse
    {
        Gate::authorize('update', $supplierConnection);

        abort_unless($this->isResendable($supplierConnection), 404);

        $supplierConnection->reinvite($supplierConnection->company_name);

        $this->sendInvitation($supplierConnection);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('suppliers.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * End the relationship.
     *
     * The row survives so the products already assigned to it keep their
     * assignment, and so the relationship can be restored later.
     */
    public function revoke(Organization $currentOrganization, SupplierConnection $supplierConnection): RedirectResponse
    {
        Gate::authorize('update', $supplierConnection);

        abort_if($supplierConnection->status === SupplierConnectionStatus::Revoked, 404);

        $supplierConnection->update(['status' => SupplierConnectionStatus::Revoked]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier disconnected.')]);

        return to_route('suppliers.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Restore a relationship the distributor ended earlier.
     */
    public function restore(Organization $currentOrganization, SupplierConnection $supplierConnection): RedirectResponse
    {
        Gate::authorize('update', $supplierConnection);

        abort_unless(
            $supplierConnection->status === SupplierConnectionStatus::Revoked && $supplierConnection->isClaimed(),
            404,
        );

        $supplierConnection->update(['status' => SupplierConnectionStatus::Active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier reconnected.')]);

        return to_route('suppliers.index', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Determine if the claim link can be sent again.
     */
    protected function isResendable(SupplierConnection $connection): bool
    {
        return ! $connection->isClaimed() && in_array(
            $connection->status,
            [SupplierConnectionStatus::Pending, SupplierConnectionStatus::Revoked],
            strict: true,
        );
    }

    /**
     * Mail the claim link to the contact address.
     */
    protected function sendInvitation(SupplierConnection $connection): void
    {
        Notification::route('mail', $connection->contact_email)
            ->notify(new SupplierConnectionInvitation($connection));
    }

    /**
     * Transform the connection for the distributor's frontend.
     *
     * The row actions are decided here rather than from status strings in the
     * page, so the button a distributor sees and the request the controller
     * accepts can never drift apart.
     *
     * @return array{id: int, companyName: string, contactEmail: string, status: string, statusLabel: string, isClaimed: bool, isAssignable: bool, canResend: bool, canRestore: bool, productsCount: int, expiresAt: string|null, createdAt: string|null}
     */
    protected function toConnectionArray(SupplierConnection $connection): array
    {
        $supplier = $connection->supplierOrganization;

        return [
            'id' => $connection->id,
            'companyName' => $supplier !== null ? $supplier->name : $connection->company_name,
            'contactEmail' => $connection->contact_email,
            'status' => $connection->status->value,
            'statusLabel' => $connection->isPending() && $connection->isExpired()
                ? __('Expired')
                : $connection->status->label(),
            'isClaimed' => $connection->isClaimed(),
            'isAssignable' => in_array($connection->status, SupplierConnectionStatus::assignable(), strict: true),
            'canResend' => $this->isResendable($connection),
            'canRestore' => $connection->status === SupplierConnectionStatus::Revoked && $connection->isClaimed(),
            'productsCount' => (int) ($connection->products_count ?? 0),
            'expiresAt' => $connection->expires_at?->toIso8601String(),
            'createdAt' => $connection->created_at?->toIso8601String(),
        ];
    }
}
