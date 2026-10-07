<?php

namespace App\Http\Controllers;

use App\Actions\Products\AssessProductDocuments;
use App\Data\ProductAssessmentView;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * AI readings of a product's papers.
 *
 * Starting one only puts it on the queue; the page then watches the latest
 * run until it finishes. Nothing here can move the review or the seal.
 */
class ProductAssessmentController extends Controller
{
    public function __construct(protected AssessProductDocuments $assess)
    {
        //
    }

    /**
     * Ask for a new run.
     */
    public function store(Request $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('assess', $product);

        $this->assess->start($product, $request->user(), $currentOrganization);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI check started. It usually takes a minute or two.')]);

        return to_route('products.edit', [
            'current_organization' => $currentOrganization->slug,
            'product' => $product->id,
        ]);
    }

    /**
     * Show one run, for looking back at an earlier one.
     */
    public function show(Organization $currentOrganization, Product $product, ProductAssessment $assessment): JsonResponse
    {
        Gate::authorize('assess', $product);

        return response()->json(ProductAssessmentView::detail($assessment));
    }
}
