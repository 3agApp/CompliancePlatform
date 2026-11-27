<?php

namespace App\Http\Controllers;

use App\Actions\Products\TranslateProductTexts;
use App\Http\Requests\Products\SuggestProductTranslationsRequest;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Translations of a product's safety texts, suggested by AI.
 */
class ProductTranslationController extends Controller
{
    /**
     * Suggest translations of the texts on the form.
     *
     * Answers 200 whatever happened. A provider that is not configured or
     * does not answer is reported in the body: the translations can still
     * be typed by hand, which is all this ever saves anybody.
     *
     * The viewing organization's provider, not the product owner's: a
     * supplier filling in a distributor's product spends its own credit.
     */
    public function suggest(SuggestProductTranslationsRequest $request, Organization $currentOrganization, Product $product, TranslateProductTexts $translate): JsonResponse
    {
        Gate::authorize('update', $product);

        return response()->json($translate->handle(
            $currentOrganization,
            $request->from(),
            $request->texts(),
            $request->targets(),
        ));
    }
}
