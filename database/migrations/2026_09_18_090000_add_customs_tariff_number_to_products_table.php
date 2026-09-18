<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The code that decides what a product is charged at the border. It is
     * stored as the bare digits it is made of, without the dots it is usually
     * written with, so that two people typing the same code the two usual
     * ways store one value.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('customs_tariff_number', 12)->nullable()->after('order_number');

            $table->index(['organization_id', 'customs_tariff_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'customs_tariff_number']);
            $table->dropColumn('customs_tariff_number');
        });
    }
};
