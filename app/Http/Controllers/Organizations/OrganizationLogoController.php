<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\SaveOrganizationLogoRequest;
use App\Models\Organization;
use App\Models\ProductDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationLogoController extends Controller
{
    /**
     * The content types a logo is served as, by file extension.
     *
     * @var array<string, string>
     */
    private const array CONTENT_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    /**
     * Show the organization's logo to anyone.
     *
     * Public, because it appears on product pages. Served from the private
     * disk all the same, with the type from the allowlist rather than from
     * whatever the uploading browser claimed.
     */
    public function show(Organization $organization): StreamedResponse
    {
        $path = $organization->logo_path;
        $disk = Storage::disk(ProductDocument::DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        $type = self::CONTENT_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        abort_if($type === null, 404);

        return $disk->response($path, 'logo.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'none'",
            'Cache-Control' => 'public, max-age=86400',
        ], 'inline');
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

        $organization->forceFill(['logo_path' => $path])->save();

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
