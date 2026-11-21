<?php

namespace App\Http\Controllers;

use App\Http\Requests\Brands\SaveBrandLogoRequest;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\ProductDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * A maker's logo, shown beside its name on the brands page and on the
 * public page of every product carrying it.
 *
 * Kept on the private disk like every other file. The public page reaches
 * it through the product, so only a brand already named in public has a
 * logo anybody can fetch without logging in.
 */
class BrandLogoController extends Controller
{
    /**
     * Show the brand's logo to somebody on either side of its trade.
     */
    public function show(Organization $currentOrganization, Brand $brand): StreamedResponse
    {
        Gate::authorize('view', $brand);

        return $brand->logoResponse(public: false);
    }

    /**
     * Upload or replace the brand's logo.
     */
    public function store(SaveBrandLogoRequest $request, Organization $currentOrganization, Brand $brand): RedirectResponse
    {
        Gate::authorize('update', $brand);

        /** @var UploadedFile $file */
        $file = $request->file('logo');

        $path = $file->store($brand->logoDirectory(), ProductDocument::DISK);

        abort_if($path === false, 500);

        $previous = $brand->logo_path;

        /**
         * A row that cannot be written would leave the new file on the disk
         * with nothing pointing at it, so it goes again before the error
         * carries on up.
         */
        try {
            $brand->forceFill(['logo_path' => $path])->save();
        } catch (Throwable $exception) {
            Storage::disk(ProductDocument::DISK)->delete($path);

            throw $exception;
        }

        if ($previous !== null && $previous !== $path) {
            Storage::disk(ProductDocument::DISK)->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo updated.')]);

        return back();
    }

    /**
     * Remove the brand's logo.
     */
    public function destroy(Organization $currentOrganization, Brand $brand): RedirectResponse
    {
        Gate::authorize('update', $brand);

        if ($brand->logo_path !== null) {
            Storage::disk(ProductDocument::DISK)->delete($brand->logo_path);

            $brand->forceFill(['logo_path' => null])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removed.')]);

        return back();
    }
}
