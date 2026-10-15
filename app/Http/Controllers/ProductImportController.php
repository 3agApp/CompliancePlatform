<?php

namespace App\Http\Controllers;

use App\Actions\Imports\StartProductImport;
use App\Data\ImportManufacturerData;
use App\Enums\ImportExistingProductAction;
use App\Enums\ImportMatchKey;
use App\Enums\ImportProvider;
use App\Http\Requests\Imports\SaveImportManufacturersRequest;
use App\Http\Requests\Imports\SaveImportSourceRequest;
use App\Imports\ImportClientFactory;
use App\Imports\ImportRequestException;
use App\Models\ImportManufacturer;
use App\Models\ImportRun;
use App\Models\ImportSource;
use App\Models\Organization;
use App\Models\ProductTemplate;
use App\Models\SupplierConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Importing the distributor's catalogue from its source system: the source, the
 * manufacturers to take, and the runs.
 *
 * Nothing here ever hands the key back. The page is told only that one is
 * stored and what its last few characters were.
 */
class ProductImportController extends Controller
{
    /**
     * How many past runs the page lists.
     */
    private const int RUNS_SHOWN = 10;

    /**
     * Show the import page.
     */
    public function show(Organization $currentOrganization): Response
    {
        Gate::authorize('manageProductImport', $currentOrganization);

        $source = $currentOrganization->importSource;
        $runs = $currentOrganization->importRuns()->with('triggeredBy')->limit(self::RUNS_SHOWN)->get();

        return Inertia::render('product-import/index', [
            'source' => $source instanceof ImportSource ? $this->toSourceArray($source) : null,
            'manufacturers' => $source instanceof ImportSource
                ? $source->manufacturers->map(fn (ImportManufacturer $manufacturer) => [
                    'externalId' => $manufacturer->external_id,
                    'name' => $manufacturer->name,
                    'supplierConnectionId' => $manufacturer->supplier_connection_id,
                    'productTemplateId' => $manufacturer->product_template_id,
                ])->values()->all()
                : [],
            'runs' => $runs->map(fn (ImportRun $run) => $this->toRunArray($run))->values()->all(),
            'hasPendingRun' => $runs->contains(fn (ImportRun $run) => $run->status->isPending()),
            'hasAiProvider' => $currentOrganization->aiSetting()->exists(),
            'availableTemplates' => $currentOrganization->productTemplates()
                ->with('category')
                ->orderBy('product_templates.name')
                ->get()
                ->map(fn (ProductTemplate $template) => [
                    'id' => $template->id,
                    'label' => $template->name,
                    'categoryName' => $template->category->name,
                ])
                ->values()
                ->all(),
            'availableConnections' => $currentOrganization->supplierConnections()
                ->assignable()
                ->with('supplierOrganization')
                ->orderBy('company_name')
                ->get()
                ->map(fn (SupplierConnection $supplier) => [
                    'id' => $supplier->id,
                    'label' => $supplier->supplierOrganization !== null ? $supplier->supplierOrganization->name : $supplier->company_name,
                ])
                ->values()
                ->all(),
            'providers' => ImportProvider::options(),
            'matchKeys' => ImportMatchKey::options(),
            'existingActions' => ImportExistingProductAction::options(),
        ]);
    }

    /**
     * Connect the source, or change how it is imported from.
     */
    public function updateSource(SaveImportSourceRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $attributes = $request->settings();

        /**
         * A blank key means the person was changing something else and
         * left the key alone, so the stored one is not touched.
         */
        if (($key = $request->apiKey()) !== null) {
            $attributes['api_key'] = $key;
        }

        $currentOrganization->importSource()->updateOrCreate([], $attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import source saved.')]);

        return to_route('product-import.show', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Disconnect the source, forgetting the key and the chosen manufacturers.
     *
     * Products already imported stay where they are.
     */
    public function destroySource(Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('manageProductImport', $currentOrganization);

        abort_if($currentOrganization->importRuns()->pending()->exists(), 409, __('Wait for the running import to finish.'));

        $currentOrganization->importSource()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import source disconnected.')]);

        return to_route('product-import.show', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * List the manufacturers the source knows, for the picker.
     *
     * Always answered with a 200: a source system that refuses is something to show
     * beside the button, not an error page, so the reason travels as data.
     */
    public function manufacturers(Organization $currentOrganization, ImportClientFactory $clients): JsonResponse
    {
        Gate::authorize('manageProductImport', $currentOrganization);

        $source = $currentOrganization->importSource;

        if (! $source instanceof ImportSource) {
            return response()->json(['manufacturers' => [], 'error' => __('Connect a source first.')]);
        }

        try {
            $manufacturers = $clients->for($source)->manufacturers();
        } catch (ImportRequestException $exception) {
            return response()->json(['manufacturers' => [], 'error' => $exception->getMessage()]);
        }

        return response()->json([
            'manufacturers' => array_map(fn (ImportManufacturerData $manufacturer) => $manufacturer->toArray(), $manufacturers),
            'error' => null,
        ]);
    }

    /**
     * Save which manufacturers are imported, and where their products go.
     */
    public function updateManufacturers(SaveImportManufacturersRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $source = $currentOrganization->importSource;

        abort_unless($source instanceof ImportSource, 404);

        $rows = $request->manufacturers();

        DB::transaction(function () use ($source, $rows) {
            $source->manufacturers()
                ->whereNotIn('external_id', array_column($rows, 'external_id'))
                ->delete();

            foreach ($rows as $row) {
                $source->manufacturers()->updateOrCreate(
                    ['external_id' => $row['external_id']],
                    $row,
                );
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Manufacturers saved.')]);

        return to_route('product-import.show', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Start an import.
     */
    public function start(Request $request, Organization $currentOrganization, StartProductImport $start): RedirectResponse
    {
        Gate::authorize('manageProductImport', $currentOrganization);

        $start->handle($currentOrganization, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import started.')]);

        return to_route('product-import.show', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Transform the source for the page, without its key.
     *
     * @return array{provider: string, apiUrl: string, keyHint: string, fileUrlPrefix: string|null, matchKey: string, onExisting: string, defaultProductTemplateId: int|null, useAiForTemplates: bool}
     */
    private function toSourceArray(ImportSource $source): array
    {
        return [
            'provider' => $source->provider->value,
            'apiUrl' => $source->api_url,
            'keyHint' => $source->keyHint(),
            'fileUrlPrefix' => $source->file_url_prefix,
            'matchKey' => $source->match_key->value,
            'onExisting' => $source->on_existing->value,
            'defaultProductTemplateId' => $source->default_product_template_id,
            'useAiForTemplates' => $source->use_ai_for_templates,
        ];
    }

    /**
     * Transform a run for the page.
     *
     * @return array<string, mixed>
     */
    private function toRunArray(ImportRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'statusLabel' => $run->status->label(),
            'isPending' => $run->status->isPending(),
            'total' => $run->total,
            'processed' => $run->processed,
            'created' => $run->created,
            'updated' => $run->updated,
            'skipped' => $run->skipped,
            'failed' => $run->failed,
            'aiAssigned' => $run->ai_assigned,
            'failures' => $run->failures ?? [],
            'failureReason' => $run->failure_reason,
            'triggeredBy' => $run->triggeredBy?->name,
            'createdAt' => $run->created_at?->toIso8601String(),
            'finishedAt' => $run->finished_at?->toIso8601String(),
        ];
    }
}
