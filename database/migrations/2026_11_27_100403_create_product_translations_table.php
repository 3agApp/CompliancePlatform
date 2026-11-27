<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A product's safety texts in one language other than the one they were
     * written in. One row per product and language, holding the same prose
     * columns the product does.
     *
     * The source hash is a fingerprint of the original texts when this
     * translation was last saved. Once the original moves on, the two stop
     * matching and the translation reads as out of date.
     */
    public function up(): void
    {
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('safety_notice')->nullable();
            $table->text('warning_text')->nullable();
            $table->text('material_information')->nullable();
            $table->text('usage_restrictions')->nullable();
            $table->text('safety_instructions')->nullable();
            $table->string('source', 10);
            $table->string('source_hash', 64);
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};
