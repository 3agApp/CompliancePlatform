<?php

namespace App\Http\Controllers\Suppliers;

use App\Enums\ProductReviewStatus;
use App\Enums\SupplierConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\InviteSupplierRequest;
use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Notifications\Suppliers\SupplierConnectionInvitation;
use Carbon\CarbonImmutable;
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
                ->withCount([
                    'products',
                    'brands',
                    ...collect(ProductReviewStatus::cases())->mapWithKeys(fn (ProductReviewStatus $status) => [
                        "products as {$status->value}_count" => fn ($query) => $query->where('review_status', $status),
                    ])->all(),
                ])
                ->withMax('products', 'updated_at')
                ->orderBy('company_name')
                ->get()
                ->map(fn (SupplierConnection $connection) => $this->toConnectionArray($connection)),
            'permissions' => $request->user()->toSupplierConnectionPermissions($currentOrganization),
        ]);
    }

    /**
     * Add a supplier, inviting them now or leaving that for later, or
     * re-open a connection that ended earlier.
     *
     * Products can be filed against a supplier before they are told about
     * it, so a distributor still setting up a catalog can add the supplier
     * without mailing anybody and send the link once there is something
     * worth showing them.
     *
     * The contact email is never looked up against existing accounts, so a
     * distributor cannot learn who is already on the platform.
     */
    public function store(InviteSupplierRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [SupplierConnection::class, $currentOrganization]);

        $companyName = $request->validated('company_name');
        $contactEmail = $request->validated('contact_email');
        $sendsInvitation = $request->sendsInvitation();

        $connection = $currentOrganization->supplierConnections()
            ->whereRaw('LOWER(contact_email) = ?', [strtolower($contactEmail)])
            ->whereNull('supplier_organization_id')
            ->first();

        if ($connection instanceof SupplierConnection) {
            $sendsInvitation
                ? $connection->reinvite($companyName)
                : $connection->reopenUninvited($companyName);
        } else {
            $connection = $currentOrganization->supplierConnections()->create([
                'company_name' => $companyName,
                'contact_email' => $contactEmail,
                'status' => SupplierConnectionStatus::Pending,
                'invited_at' => $sendsInvitation ? now() : null,
                'invited_by' => $request->user()->id,
                'expires_at' => $sendsInvitation ? now()->addDays(SupplierConnection::CLAIM_EXPIRY_DAYS) : null,
            ]);
        }

        if ($sendsInvitation) {
            $this->sendInvitation($connection);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $sendsInvitation
            ? __('Invitation sent.')
            : __('Supplier added. Invite them whenever you are ready.')]);

        /**
         * Back where the request came from: a supplier is added from its
         * own list, and inline on a product form that must come back to
         * itself with what was already typed into it still there.
         */
        return back(fallback: route('suppliers.index', ['current_organization' => $currentOrganization->slug]));
    }

    /**
     * Send the claim link, for the first time or again, with a fresh token.
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

        return back(fallback: route('suppliers.index', ['current_organization' => $currentOrganization->slug]));
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
     *
     * Written in the distributor's language: the recipient has no account
     * to have chosen one with, and the distributor knows who they trade
     * with.
     */
    protected function sendInvitation(SupplierConnection $connection): void
    {
        Notification::route('mail', $connection->contact_email)
            ->notify((new SupplierConnectionInvitation($connection))->locale($connection->distributorOrganization->locale->value));
    }

    /**
     * Transform the connection for the distributor's frontend.
     *
     * The row actions are decided here rather than from status strings in the
     * page, so the button a distributor sees and the request the controller
     * accepts can never drift apart.
     *
     * @return array{id: int, companyName: string, contactEmail: string, status: string, statusLabel: string, isClaimed: bool, isInvited: bool, isExpired: bool, isAssignable: bool, canResend: bool, canRestore: bool, productsCount: int, brandsCount: int, productsByStatus: array<string, int>, lastActivityAt: string|null, isQuiet: bool, expiresAt: string|null, createdAt: string|null}
     */
    protected function toConnectionArray(SupplierConnection $connection): array
    {
        $supplier = $connection->supplierOrganization;

        $lastActivity = ($updated = $connection->getAttribute('products_max_updated_at')) === null
            ? null
            : CarbonImmutable::parse($updated);

        return [
            'id' => $connection->id,
            'companyName' => $supplier !== null ? $supplier->name : $connection->company_name,
            'contactEmail' => $connection->contact_email,
            'status' => $connection->status->value,
            'statusLabel' => $connection->statusLabel(),
            'isClaimed' => $connection->isClaimed(),
            'isInvited' => $connection->isInvited(),
            'isExpired' => $connection->isPending() && $connection->isInvited() && $connection->isExpired(),
            'isAssignable' => in_array($connection->status, SupplierConnectionStatus::assignable(), strict: true),
            'canResend' => $this->isResendable($connection),
            'canRestore' => $connection->status === SupplierConnectionStatus::Revoked && $connection->isClaimed(),
            'productsCount' => (int) ($connection->products_count ?? 0),
            'brandsCount' => (int) ($connection->getAttribute('brands_count') ?? 0),
            /**
             * How far the supplier's products have got, so the list shows
             * who is keeping up without a trip to the products page.
             */
            'productsByStatus' => collect(ProductReviewStatus::cases())
                ->mapWithKeys(fn (ProductReviewStatus $status) => [$status->value => (int) ($connection->getAttribute("{$status->value}_count") ?? 0)])
                ->all(),
            'lastActivityAt' => $lastActivity?->toIso8601String(),
            /**
             * Drafts that have not moved in a while are the sign a supplier
             * has forgotten them; the dashboard uses the same rule.
             */
            'isQuiet' => $connection->isActive()
                && (int) ($connection->getAttribute('draft_count') ?? 0) > 0
                && ($lastActivity === null || $lastActivity->lt(now()->subDays(SupplierConnection::QUIET_AFTER_DAYS))),
            'expiresAt' => $connection->expires_at?->toIso8601String(),
            'createdAt' => $connection->created_at?->toIso8601String(),
        ];
    }
}
