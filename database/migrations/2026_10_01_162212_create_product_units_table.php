<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One physical packet, known by the serial on the label on its box and
     * in the QR code beside it. Scanning only fills the serial in; a buyer
     * checks it on purpose, and every check is kept. A copied label then
     * gives itself away: the next buyer sees it was checked before, when,
     * and from how many devices.
     */
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_batch_id')->constrained()->cascadeOnDelete();

            /**
             * Twelve Crockford base32 characters, about sixty bits: printed
             * as XXXX-XXXX-XXXX, stored without the dashes. Unique across
             * the platform, so the check page needs nothing but the serial,
             * and long enough that nobody finds a valid one by counting.
             */
            $table->char('serial', 12)->unique();

            /**
             * When it was first checked, kept on the row so a run can count
             * its checked packets without reading every check.
             */
            $table->timestamp('first_checked_at')->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['label_batch_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
