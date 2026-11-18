<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\DocumentPreviewKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\SaveOrganizationLogoRequest;
use App\Models\Organization;
use App\Models\ProductDocument;
use App\Support\PublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OrganizationLogoController extends Controller
{
    /**
     * Show the organization's logo.
     *
     * A distributor's logo is public, because product pages show it as the
     * company placing them on the market. A supplier's never appears there
     * -- who supplies a product stays theirs -- so only its own members may
     * see it, and anybody else gets a 404 rather than confirmation that the
     * supplier is here at all.
     */
    public function show(Request $request, Organization $organization): StreamedResponse
    {
        $path = $organization->logo_path;
        $public = $organization->isDistributor();

        abort_if($path === null, 404);
        abort_unless($public || $request->user()?->belongsToOrganization($organization), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = DocumentPreviewKind::forContentType('image/'.$extension) === DocumentPreviewKind::Image
            ? DocumentPreviewKind::contentTypeFor('image/'.$extension)
            : null;

        abort_if($contentType === null, 404);

        return PublicImage::response(ProductDocument::DISK, $path, 'logo.'.$extension, $contentType, $public);
    }

    /**
     * Upload or replace the organization's logo.
     */
    public function store(SaveOrganizationLogoRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('update', $organization);

        /** @var UploadedFile $file */
        $file = $request->file('logo');

        $path = $file->store($organization->logoDirectory(), ProductDocument::DISK);

        abort_if($path === false, 500);

        $previous = $organization->logo_path;

        /**
         * A row that cannot be written would leave the new file on the disk
         * with nothing pointing at it, so it goes again before the error
         * carries on up.
         */
        try {
            $organization->forceFill(['logo_path' => $path])->save();
        } catch (Throwable $exception) {
            Storage::disk(ProductDocument::DISK)->delete($path);

            throw $exception;
        }

        if ($previous !== null && $previous !== $path) {
            Storage::disk(ProductDocument::DISK)->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo updated.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }

    /**
     * Remove the organization's logo.
     */
    public function destroy(Organization $organization): RedirectResponse
    {
        Gate::authorize('update', $organization);

        if ($organization->logo_path !== null) {
            Storage::disk(ProductDocument::DISK)->delete($organization->logo_path);

            $organization->forceFill(['logo_path' => null])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removed.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }
}
