<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The manufacturers a distributor chose to import. A row is the choice:
     * no rows means nothing is imported, rather than everything, so a fresh
     * source cannot pull in a whole catalogue by accident.
     */
    public function up(): void
    {
        Schema::create('import_manufacturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_source_id')->constrained()->cascadeOnDelete();

            /**
             * Kept as the source sends it. Kontor's ids carry leading zeros.
             */
            $table->string('external_id', 64);
            $table->string('name');

            /**
             * Which supplier the manufacturer's products go to, when the
             * distributor said. Otherwise one is found or made by name.
             */
            $table->foreignId('supplier_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_template_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->unique(['import_source_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_manufacturers');
    }
};
