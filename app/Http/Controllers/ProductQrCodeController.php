<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Product;
use App\Support\ProductQrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The artwork that puts a product's public page on the product itself.
 *
 * Three ways out, because they are for three different people: a PNG for
 * whoever is dropping it into a listing, an SVG for whoever is laying out
 * the packaging and needs it to survive being scaled, and an A6 sheet for
 * whoever just wants something to print and put in the box.
 *
 * All three encode the same address the public page answers to, so a code
 * printed today still works after the product has been edited, reviewed,
 * approved and edited again.
 */
class ProductQrCodeController extends Controller
{
    /**
     * The paper the printable sheet is laid out for.
     *
     * A6 is a postcard: big enough that the code scans off a phone at arm's
     * length, small enough to sit in a box beside the product.
     */
    protected const string PAPER = 'a6';

    /**
     * Hand over the product's code as a picture.
     *
     * Served inline rather than as an attachment, so the page that offers it
     * can also show it: the download itself is the link's business, and a
     * preview nobody can see is how a wrong code gets printed.
     */
    public function show(Organization $currentOrganization, Product $product, string $format): Response
    {
        Gate::authorize('downloadLabel', $product);

        $code = $format === 'svg'
            ? ProductQrCode::svg($product)
            : ProductQrCode::png($product);

        return response($code->getString(), 200, [
            'Content-Type' => $code->getMimeType(),
            'Content-Disposition' => 'inline; filename="'.ProductQrCode::filename($product, $format).'"',
            'X-Content-Type-Options' => 'nosniff',
            /**
             * The code never changes -- it encodes a uuid that never changes
             * -- but the product it is about does, so it is not cached
             * anywhere shared.
             */
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Hand over the printable A6 sheet.
     *
     * It carries what the article is and the code that leads to the rest.
     * Deliberately not the seal: this sheet goes into a box and stays there,
     * while the seal can change the same afternoon the product is edited, so
     * anything printed about where the check stands is a claim that goes
     * stale on a shelf. The scan is what answers that question, freshly,
     * every time.
     */
    public function label(Organization $currentOrganization, Product $product): Response
    {
        Gate::authorize('downloadLabel', $product);

        $product->load('brand');

        /**
         * Without this the renderer embeds the whole of every font it
         * touches, which is a megaphone of a file for a sheet carrying
         * forty characters: a label goes from about 1.1 MB to about 27 KB.
         */
        $pdf = Pdf::setOption(['isFontSubsettingEnabled' => true])->loadView('products.label', [
            'product' => $product,
            'url' => $product->publicUrl(),
            /**
             * Embedded rather than linked: the renderer would otherwise have
             * to fetch the picture back off this application over the
             * network, as a request carrying none of the session that is
             * allowed to ask for it.
             */
            'qr' => ProductQrCode::dataUri($product),
        ])->setPaper(self::PAPER);

        return $pdf->download(ProductQrCode::filename($product, 'pdf'));
    }
}
