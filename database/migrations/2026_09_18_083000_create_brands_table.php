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
     * becomes a row the organization keeps, the way its legal families
     * already are.
     *
     * Unlike the legal families there is no starting list: a family comes
     * from regulation and repeats across the platform, while the brands an
     * organization carries are its own and nobody can guess them.
     */
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            /**
             * Also the index the organization's own list is read by, so no
             * separate index on organization_id is needed.
             */
            $table->unique(['organization_id', 'name']);
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
     */
    protected function promoteTheBrandsAlreadyTyped(): void
    {
        $typed = DB::table('products')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->select('organization_id', 'brand')
            ->distinct()
            ->get();

        $now = now();
        $created = [];

        foreach ($typed as $row) {
            $key = $row->organization_id.'|'.mb_strtolower($row->brand);

            $created[$key] ??= DB::table('brands')->insertGetId([
                'organization_id' => $row->organization_id,
                'name' => $row->brand,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('products')
                ->where('organization_id', $row->organization_id)
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
