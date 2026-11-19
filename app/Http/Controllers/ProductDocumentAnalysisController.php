<?php

namespace App\Http\Controllers;

use App\Actions\Products\AnalyzeProductDocument;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * AI readings of a single paper.
 *
 * Starting one only puts it on the queue; the documents tab then watches the
 * paper until the run finishes. Nothing here can move the review or the seal.
 */
class ProductDocumentAnalysisController extends Controller
{
    public function __construct(protected AnalyzeProductDocument $analyze)
    {
        //
    }

    /**
     * Ask for a new run on one paper.
     */
    public function store(Request $request, Organization $currentOrganization, Product $product, ProductDocument $document): RedirectResponse
    {
        Gate::authorize('assess', $product);

        /**
         * The product's own organization, not whichever one is in the URL,
         * for the same reason as ProductAssessmentController::store().
         */
        $this->analyze->start($document, $request->user(), $product->organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI analysis started. It usually takes under a minute.')]);

        return to_route('products.edit', [
            'current_organization' => $currentOrganization->slug,
            'product' => $product->id,
            'tab' => 'documents',
        ]);
    }
}
