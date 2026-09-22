<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The name a product goes by in public.
     *
     * The public page is reachable by anyone holding the link -- off a
     * packet, a label, a QR code -- so it cannot be addressed by the row id:
     * that would let a reader walk the whole catalogue of every distributor
     * on the platform by counting. A uuid is unguessable and says nothing
     * about how many products there are or when this one was added.
     *
     * Added to every product that already exists, then made unique: a
     * product without one would have no public page at all.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('products')->whereNull('uuid')->orderBy('id')->each(function (object $product) {
            DB::table('products')->where('id', $product->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
