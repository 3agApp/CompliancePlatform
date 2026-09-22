<?php

namespace App\Http\Controllers;

use App\Enums\ProductEventType;
use App\Http\Requests\Products\OverrideProductSealRequest;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Setting a product's public seal by hand, and handing it back to the
 * review.
 *
 * An override is the distributor's own word on a page anybody can read, so
 * it is never a quiet change: who set it, what they set it to and why all go
 * into the product's history, and the public page says the seal was set by
 * hand rather than earned.
 */
class ProductSealController extends Controller
{
    /**
     * Set or clear the seal the product shows in public.
     */
    public function update(OverrideProductSealRequest $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('overrideSeal', $product);

        $seal = $request->seal();
        $reason = $seal === null ? null : $request->validated('reason');
        $actor = $request->user();

        DB::transaction(function () use ($product, $seal, $reason, $actor, $currentOrganization) {
            $previous = $product->seal_override;

            $product->forceFill([
                'seal_override' => $seal,
                'seal_override_reason' => $reason,
                'seal_overridden_by' => $seal === null ? null : $actor->id,
                'seal_overridden_at' => $seal === null ? null : now(),
            ])->save();

            $product->recordEvent(
                $seal === null ? ProductEventType::SealOverrideCleared : ProductEventType::SealOverridden,
                $actor,
                $currentOrganization,
                note: $reason,
                changes: ['seal_override' => [
                    'from' => $previous?->label(),
                    'to' => $seal?->label(),
                ]],
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $seal === null
                ? __('The seal follows the review again.')
                : __('Public seal updated.'),
        ]);

        return to_route('products.edit', [
            'current_organization' => $currentOrganization->slug,
            'product' => $product->id,
        ]);
    }
}
