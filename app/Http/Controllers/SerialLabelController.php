<?php

namespace App\Http\Controllers;

use App\Models\LabelBatch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\ProductQrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Runs of serialised labels: one serial for every packet in a shipment.
 *
 * One label per packet, for the box: the serial, and a QR code that leads
 * to it. A label copied off one box onto others is caught the moment a
 * second buyer sees its serial was checked before.
 */
class SerialLabelController extends Controller
{
    /**
     * Points in a millimetre, for laying the label out at its exact size.
     */
    protected const float POINTS_PER_MM = 72 / 25.4;

    /**
     * How wide each packet's QR code is drawn, in pixels. Printed at about
     * two centimetres, so the full-size artwork would only slow a run down.
     */
    protected const int QR_SIZE = 240;

    /**
     * Issue a new run.
     */
    public function store(Request $request, Organization $currentOrganization, Product $product): RedirectResponse
    {
        Gate::authorize('manageSerialLabels', $product);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('labels.max_batch')],
        ]);

        LabelBatch::issue($product, (int) $validated['quantity'], $request->user(), $currentOrganization);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Labels issued. Download them below.')]);

        return to_route('products.edit', [
            'current_organization' => $currentOrganization->slug,
            'product' => $product->id,
        ]);
    }

    /**
     * Hand over a run as a PDF, one label per page at the roll's size.
     *
     * Can be fetched again as often as needed -- a jammed roll is the usual
     * reason -- and prints the same serials every time.
     */
    public function pdf(Organization $currentOrganization, Product $product, LabelBatch $labelBatch): Response
    {
        Gate::authorize('manageSerialLabels', $product);

        abort_if($labelBatch->revoked_at !== null, 404);

        $product->load('brand');

        $units = $labelBatch->units()->get()->each(fn (ProductUnit $unit) => $unit->setRelation('product', $product));

        $pdf = Pdf::setOption(['isFontSubsettingEnabled' => true])->loadView('products.unit-labels', [
            'product' => $product,
            'units' => $units->map(fn (ProductUnit $unit) => [
                'serial' => $unit->formattedSerial(),
                'qr' => ProductQrCode::dataUriFor($unit->url(), self::QR_SIZE),
            ]),
        ])->setPaper([
            0,
            0,
            config('labels.width_mm') * self::POINTS_PER_MM,
            config('labels.height_mm') * self::POINTS_PER_MM,
        ]);

        $name = Str::slug($product->name) ?: 'product';

        return $pdf->download("{$name}-labels-{$labelBatch->uuid}.pdf");
    }

    /**
     * Withdraw every label in a run, for a roll that went missing or a run
     * printed by mistake.
     */
    public function destroy(Request $request, Organization $currentOrganization, Product $product, LabelBatch $labelBatch): RedirectResponse
    {
        Gate::authorize('manageSerialLabels', $product);

        if ($labelBatch->revoked_at === null) {
            $labelBatch->setRelation('product', $product)->revoke($request->user(), $currentOrganization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Labels withdrawn.')]);

        return to_route('products.edit', [
            'current_organization' => $currentOrganization->slug,
            'product' => $product->id,
        ]);
    }
}
