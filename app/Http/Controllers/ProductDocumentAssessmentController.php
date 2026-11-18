<?php

namespace App\Http\Controllers;

use App\Actions\Products\AssessProductDocuments;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * AI readings of one of a product's papers on its own.
 *
 * The same advisory check as the product's, narrowed to a single paper, so
 * a reviewer can have a new test report read the moment it arrives without
 * paying to have every other paper read again.
 */
class ProductDocumentAssessmentController extends Controller
{
    /**
     * Ask for a run of one paper.
     */
    public function store(
        Request $request,
        Organization $currentOrganization,
        Product $product,
        ProductDocument $document,
        AssessProductDocuments $assess,
    ): RedirectResponse {
        Gate::authorize('assess', $product);

        /**
         * The product's own organization pays for it, never whichever one
         * is in the URL, for the same reason as the product's own check.
         */
        $assess->start($product, $request->user(), $product->organization, $document);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI check of :name started. It usually takes a minute.', ['name' => $document->name])]);

        return back();
    }
}
