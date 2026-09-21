<?php

namespace App\Actions\Organizations;

use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteOrganization
{
    /**
     * Delete an organization and wind down what it was party to.
     *
     * The foreign keys carry away everything that belongs to the company
     * alone -- its memberships, invitations, categories, templates, supplier
     * connections, brands and AI provider. Two things they cannot, and this
     * handles both.
     *
     * @param  User|null  $keeping  a user to leave alone, because the caller
     *                              switches them somewhere else afterwards
     */
    public function handle(Organization $organization, ?User $keeping = null): void
    {
        DB::transaction(function () use ($organization, $keeping) {
            User::query()
                ->where('current_organization_id', $organization->id)
                ->when($keeping, fn (Builder $query, User $user) => $query->where('id', '!=', $user->id))
                ->each(fn (User $affectedUser) => $affectedUser->switchToFallbackOrganization($organization));

            /**
             * The trades where this company was the supplier belong to the
             * other side, whose products go on existing. The foreign key
             * only empties the supplier out of them, which would leave them
             * reading as live connections to nobody, so they are ended here.
             */
            $organization->distributorConnections()->update([
                'status' => SupplierConnectionStatus::Revoked,
            ]);

            $this->deleteProducts($organization);

            $organization->delete();
        });
    }

    /**
     * Take the company's products, and the files filed against them.
     *
     * Products have to go first and by hand. They hold a restrict on the
     * connection, the category and the template they were filed under, all
     * of which the organization's own cascade is about to remove -- so the
     * delete would be refused with the products still pointing at them.
     *
     * The document rows follow by cascade, but a cascade fires no model
     * event and so would leave every file on the disk with nothing pointing
     * at it. The directories go first, the same way ProductController does
     * it for one product.
     */
    private function deleteProducts(Organization $organization): void
    {
        $organization->products()
            ->select(['id'])
            ->chunkById(100, function ($products): void {
                $products->each(fn (Product $product) => Storage::disk(ProductDocument::DISK)
                    ->deleteDirectory(ProductDocument::directoryFor($product)));

                Product::whereKey($products->modelKeys())->delete();
            });
    }
}
