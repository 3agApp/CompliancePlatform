<?php

namespace App\Http\Controllers;

use App\Models\LabelBatch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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
            'issued_for' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'issued_for.required' => __('Say who or what these labels are for, such as a customer, shipment or order.'),
        ]);

        LabelBatch::issue(
            $product,
            (int) $validated['quantity'],
            $validated['issued_for'],
            $validated['note'] ?? null,
            $request->user(),
            $currentOrganization,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Labels issued. Download them below.')]);

        return to_route('products.edit', [
            'current_organization' => $currentOrganization->slug,
            'product' => $product->id,
        ]);
    }

    /**
     * Show how a run's labels are being checked.
     *
     * Every packet in the run with how often it was checked and from how
     * many devices, most-checked first. A buyer checks a new box once or
     * twice; a serial checked far more often than that is a label that was
     * copied or handed round, and the run it came off says where it went.
     */
    public function show(Organization $currentOrganization, Product $product, LabelBatch $labelBatch): InertiaResponse
    {
        Gate::authorize('manageSerialLabels', $product);

        $labelBatch->load('creator');
        $threshold = (int) config('labels.unusual_checks');

        $units = $labelBatch->units()
            ->withCount([
                'checks',
                'checks as devices_count' => fn ($query) => $query->select(DB::raw('count(distinct device_hash)')),
            ])
            ->withMax('checks', 'created_at')
            ->get()
            ->sortBy([['checks_count', 'desc'], ['id', 'asc']])
            ->values();

        $checks = (int) $units->sum('checks_count');

        return Inertia::render('products/label-batch', [
            'product' => ['id' => $product->id, 'name' => $product->name],
            'batch' => [
                'id' => $labelBatch->id,
                'quantity' => $labelBatch->quantity,
                'issuedFor' => $labelBatch->issued_for,
                'note' => $labelBatch->note,
                'createdAt' => $labelBatch->created_at?->toIso8601String(),
                'createdBy' => $labelBatch->creator?->name,
                'revokedAt' => $labelBatch->revoked_at?->toIso8601String(),
            ],
            'summary' => [
                'checked' => $units->where('checks_count', '>', 0)->count(),
                'checks' => $checks,
                'unusual' => $units->where('checks_count', '>=', $threshold)->count(),
            ],
            'unusualThreshold' => $threshold,
            'units' => $units->map(fn (ProductUnit $unit) => [
                'id' => $unit->id,
                'serial' => $unit->formattedSerial(),
                'checks' => (int) $unit->getAttribute('checks_count'),
                'devices' => (int) $unit->getAttribute('devices_count'),
                'firstCheckedAt' => $unit->first_checked_at?->toIso8601String(),
                'lastCheckedAt' => $unit->getAttribute('checks_max_created_at') === null
                    ? null
                    : Date::parse($unit->getAttribute('checks_max_created_at'))->toIso8601String(),
                'revoked' => $unit->revoked_at !== null,
            ])->all(),
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
                'qr' => QrCode::dataUri($unit->url(), self::QR_SIZE),
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
