<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\SaveOrganizationAiSettingRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The AI provider an organization pays for itself.
 *
 * Nothing here ever hands the key back. Once it is stored the page is told
 * only which provider and model are set and what the last few characters
 * were, which is enough to recognise a key and useless to anyone reading
 * over a shoulder.
 */
class OrganizationAiSettingController extends Controller
{
    /**
     * Connect a provider, or change the one already connected.
     */
    public function update(SaveOrganizationAiSettingRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('manageAiProvider', $organization);

        $attributes = [
            'provider' => $request->validated('provider'),
            'model' => $request->validated('model'),
        ];

        /**
         * A blank key means the person was changing the model and left the
         * key alone, so the stored one is not touched.
         */
        if (($key = $request->apiKey()) !== null) {
            $attributes['api_key'] = $key;
        }

        $organization->aiSetting()->updateOrCreate([], $attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI provider saved.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }

    /**
     * Disconnect the provider, and forget the key with it.
     */
    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('manageAiProvider', $organization);

        $organization->aiSetting()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI provider disconnected.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }
}
