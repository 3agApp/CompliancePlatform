<?php

namespace App\Actions\Imports;

use App\Enums\SupplierConnectionStatus;
use App\Models\ImportManufacturer;
use App\Models\Organization;
use App\Models\SupplierConnection;
use App\Models\User;

/**
 * Find the supplier an imported manufacturer's products are filed against, or
 * add one.
 *
 * The distributor's own choice comes first. Without one, a supplier already
 * known by the same name is used, and only then is a new one added -- with
 * no address and no invitation, because the source knows neither. Whatever is
 * settled on is written back to the manufacturer, so the next import files
 * the same products against the same supplier, and the distributor can see
 * and change which one that is.
 */
class ResolveImportSupplier
{
    /**
     * @throws ImportRowException when the chosen supplier can no longer take products.
     */
    public function handle(ImportManufacturer $manufacturer, Organization $organization, ?User $actor): SupplierConnection
    {
        $connection = $manufacturer->supplierConnection;

        if ($connection instanceof SupplierConnection) {
            if (! in_array($connection->status, SupplierConnectionStatus::assignable(), strict: true)) {
                throw new ImportRowException(__('The supplier :name is disconnected and cannot take products.', ['name' => $connection->company_name]));
            }

            return $connection;
        }

        $connection = $organization->supplierConnections()
            ->whereIn('status', SupplierConnectionStatus::assignableValues())
            ->whereRaw('LOWER(company_name) = ?', [mb_strtolower($manufacturer->name)])
            ->orderBy('id')
            ->first();

        if (! $connection instanceof SupplierConnection) {
            $inviter = $actor ?? $organization->owner();

            $connection = $organization->supplierConnections()->create([
                'company_name' => $manufacturer->name,
                'contact_email' => null,
                'status' => SupplierConnectionStatus::Pending,
                'invited_at' => null,
                'invited_by' => $inviter?->getKey(),
                'expires_at' => null,
            ]);
        }

        $manufacturer->supplierConnection()->associate($connection)->save();

        return $connection;
    }
}
