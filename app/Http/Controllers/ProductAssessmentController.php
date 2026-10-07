<?php

namespace App\Http\Controllers;

use App\Actions\Products\AssessProductDocuments;
use App\Data\ProductAssessmentView;
use App\Enums\AssessmentStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAssessment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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

        /**
         * The product's own organization, not whichever one is in the URL:
         * someone who reviews for the distributor and also belongs to the
         * supplier could otherwise run the check on the supplier's key.
         */
        $this->assess->start($product, $request->user(), $product->organization);

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

    /**
     * Hand over a finished run as a PDF report.
     *
     * Only a finished run: a run still going has nothing to report, and a
     * failed one has only its reason, which the page already shows.
     */
    public function report(Organization $currentOrganization, Product $product, ProductAssessment $assessment): Response
    {
        Gate::authorize('assess', $product);

        abort_unless($assessment->status === AssessmentStatus::Completed, 404);

        $assessment->setRelation('product', $product);

        $pdf = Pdf::setOption(['isFontSubsettingEnabled' => true])
            ->loadView('products.assessment-report', ProductAssessmentView::report($assessment))
            ->setPaper('a4');

        $name = Str::slug($product->name) ?: 'product';

        return $pdf->download("{$name}-ai-check-{$assessment->id}.pdf");
    }
}
