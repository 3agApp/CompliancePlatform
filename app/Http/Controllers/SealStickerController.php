<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Adapter\CPDF;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Sheets of the round seal sticker: the Swiss Product Seal itself, with
 * nothing of any one product on it, for sticking beside a product's own
 * label.
 */
class SealStickerController extends Controller
{
    /**
     * Show the page sheets of seals are printed from.
     */
    public function index(Organization $currentOrganization): InertiaResponse
    {
        Gate::authorize('printSealStickers', $currentOrganization);

        return Inertia::render('seal-stickers/index', [
            'perSheet' => self::perSheet(),
            'maxSheets' => (int) config('labels.max_seal_sheets'),
        ]);
    }

    /**
     * Hand over sheets of seals as a PDF, shown in the browser so the page
     * that asked for it can send it straight to the printer.
     */
    public function pdf(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('printSealStickers', $currentOrganization);

        $validated = $request->validate([
            'sheets' => ['sometimes', 'integer', 'min:1', 'max:'.config('labels.max_seal_sheets')],
        ]);

        $pdf = Pdf::loadView('seal-stickers', [
            'grid' => config('labels.seal_sheet'),
            'sheets' => (int) ($validated['sheets'] ?? 1),
            'seal' => public_path('brand/siegel_farbig_hell.png'),
        ])->setPaper('a4');

        $pdf->render();

        /**
         * Ask the printer for actual size. Browsers otherwise tend to fit a
         * PDF to the printable area, which shrinks the seals off the sheet's
         * die-cut circles.
         */
        $canvas = $pdf->getDomPDF()->getCanvas();

        if ($canvas instanceof CPDF) {
            $canvas->get_cpdf()->setPreferences(['PrintScaling' => 'None']);
        }

        return $pdf->stream('swiss-product-seal-stickers.pdf');
    }

    /**
     * How many seals one sheet carries.
     */
    public static function perSheet(): int
    {
        $grid = config('labels.seal_sheet');

        return $grid['columns'] * $grid['rows'];
    }
}
