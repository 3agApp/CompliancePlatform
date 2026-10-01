<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\UnitSerial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page a serial can be typed into, for a label whose QR code will not
 * scan or a buyer who would rather check by hand.
 *
 * Its only answer is to send the reader to the packet's own page. A serial
 * nobody issued gets no page at all, just the warning: there is no product
 * to show for it.
 */
class CheckUnitController extends Controller
{
    /**
     * Show the page.
     */
    public function show(): Response
    {
        return Inertia::render('check', ['locale' => App::getLocale()]);
    }

    /**
     * Look up a typed serial.
     *
     * Asked from the product's own page, the serial has to be one of that
     * product's: a label copied off another article is not genuine for this
     * one, whatever it is genuine for.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'serial' => ['required', 'string', 'max:32'],
            'product' => ['nullable', 'string', 'uuid'],
        ]);

        $serial = UnitSerial::normalize((string) $request->input('serial'));

        $unit = $serial === null ? null : ProductUnit::query()
            ->where('serial', $serial)
            ->when($request->filled('product'), fn ($query) => $query->whereIn(
                'product_id',
                Product::query()->select('id')->where('uuid', $request->input('product')),
            ))
            ->with('product')
            ->first();

        if ($unit === null) {
            throw ValidationException::withMessages([
                'serial' => __('This code is not registered. The product may not be genuine.'),
            ]);
        }

        return redirect()->to($unit->url());
    }
}
