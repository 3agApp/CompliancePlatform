<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The brand was free text on the product, so the same maker was spelled
     * three ways across a catalog and nothing could be counted by it. It
     * becomes a row, the way the legal families already are.
     *
     * It hangs off the supplier connection rather than off an organization,
     * because a brand is the maker as known through one trading
     * relationship: it is the supplier's to name, and the distributor sees
     * it on the products that supplier is responsible for. Two suppliers
     * both carrying the same maker are two rows, which is right -- each
     * side answers for its own.
     *
     * The connection rather than the supplier organization, for the same
     * reason a product is assigned to one: a supplier who has not claimed
     * their invitation yet has no organization, and their products have to
     * be able to name a brand all the same.
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

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')
                ->nullable()
                ->after('name')
                ->constrained()
                ->nullOnDelete();

            $table->index('brand_id');
        });

        $this->promoteTheBrandsAlreadyTyped();

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }

    /**
     * Turn the brand names already on products into rows.
     *
     * Spellings that differ only in case are one brand, and the first one
     * seen wins: the application compares names case insensitively, so
     * keeping "Alpro" and "alpro" apart here would produce a pair no one
     * could rename afterwards.
     *
     * A product with no supplier yet has nothing to hang its brand off, so
     * the name is left on it rather than promoted. Nothing is lost that was
     * not already free text.
     */
    protected function promoteTheBrandsAlreadyTyped(): void
    {
        $typed = DB::table('products')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->whereNotNull('supplier_connection_id')
            ->select('supplier_connection_id', 'brand')
            ->distinct()
            ->get();

        $now = now();
        $created = [];

        foreach ($typed as $row) {
            $key = $row->supplier_connection_id.'|'.mb_strtolower($row->brand);

            $created[$key] ??= DB::table('brands')->insertGetId([
                'supplier_connection_id' => $row->supplier_connection_id,
                'name' => $row->brand,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('products')
                ->where('supplier_connection_id', $row->supplier_connection_id)
                ->where('brand', $row->brand)
                ->update(['brand_id' => $created[$key]]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
        });

        foreach (DB::table('brands')->get() as $brand) {
            DB::table('products')->where('brand_id', $brand->id)->update(['brand' => $brand->name]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropIndex(['brand_id']);
            $table->dropColumn('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};
