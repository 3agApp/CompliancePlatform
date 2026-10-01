<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every time somebody checked a serial: the history a buyer is shown
     * when the code in their hand has been checked before.
     *
     * No address and no user agent: a keyed hash of a random cookie is
     * enough to tell "this device" from "another device", and it names
     * nobody.
     */
    public function up(): void
    {
        Schema::create('product_unit_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->char('device_hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->index(['product_unit_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_unit_checks');
    }
};
