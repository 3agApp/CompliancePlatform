<?php

namespace App\Http\Controllers;

use App\Actions\Products\ReviewProduct;
use App\Http\Requests\Products\ReopenProductReviewRequest;
use App\Http\Requests\Products\RequestProductChangesRequest;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Handing a product between the two sides of a trade: the supplier offers it
 * up, the distributor signs it off or sends it back.
 *
 * Every action here is a move rather than an edit, so each one is a POST to
 * its own address and none of them take anything but the reviewer's note.
 * Two people can reach the same product at once, so the move the product is
 * actually in is checked here as well as offered by the page: a button that
 * was right when the page was drawn is not proof that it is still right.
 */
class ProductReviewController extends Controller
{
    public function __construct(protected ReviewProduct $review)
    {
        //
    }

    /**
     * Offer the product up for review.
     */
    public function submit(Request $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('submit', $product);

        abort_unless($product->review_status->isSubmittable(), 409);

        $this->review->submit($product, $request->user(), $currentOrganization);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Submitted for review.')]);

        return $this->backToProduct($currentOrganization, $product);
    }

    /**
     * Sign the product off.
     */
    public function approve(Request $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('review', $product);

        abort_unless($product->review_status->isReviewable(), 409);

        $this->review->approve($product, $request->user(), $currentOrganization);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product approved.')]);

        return $this->backToProduct($currentOrganization, $product);
    }

    /**
     * Send the product back with a note saying what is still needed.
     */
    public function requestChanges(RequestProductChangesRequest $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('review', $product);

        abort_unless($product->review_status->isReviewable(), 409);

        $this->review->requestChanges(
            $product,
            $request->user(),
            $currentOrganization,
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sent back to the supplier.')]);

        return $this->backToProduct($currentOrganization, $product);
    }

    /**
     * Take back the sign-off on an approved product.
     */
    public function reopen(ReopenProductReviewRequest $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('review', $product);

        abort_unless($product->review_status->isReopenable(), 409);

        $this->review->reopen(
            $product,
            $request->user(),
            $currentOrganization,
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval taken back. The product is in review again.')]);

        return $this->backToProduct($currentOrganization, $product);
    }

    /**
     * Send the reviewer back to the product they acted on.
     */
    protected function backToProduct(Organization $currentOrganization, Product $product): RedirectResponse
    {
        return to_route('products.edit', [
            'current_organization' => $currentOrganization->slug,
            'product' => $product->id,
        ]);
    }
}
