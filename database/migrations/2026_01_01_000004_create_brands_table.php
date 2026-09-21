<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The maker behind a product, as a row rather than as free text: typed
     * by hand the same maker is spelled three ways across a catalog and
     * nothing can be counted by it.
     *
     * A brand hangs off a supplier connection rather than off an
     * organization, because it is the maker as known through one trading
     * relationship: it is the supplier's to name, and the distributor sees
     * it on the products that supplier is responsible for. Two suppliers
     * both carrying the same maker are two rows, which is right -- each side
     * answers for its own. The connection rather than the supplier
     * organization, for the same reason a product is assigned to one: a
     * supplier who has not claimed their invitation yet has no organization,
     * and their products have to be able to name a brand all the same.
     *
     * Unlike the legal families there is no starting list: a family comes
     * from regulation and repeats across the platform, while the makers
     * behind a trade are its own and nobody can guess them.
     */
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_connection_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            /**
             * Also the index one connection's own list is read by, so no
             * separate index on supplier_connection_id is needed.
             */
            $table->unique(['supplier_connection_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
