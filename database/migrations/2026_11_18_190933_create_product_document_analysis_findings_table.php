<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One gap a reading of a single paper found, with the reason it was
     * flagged and what to ask the manufacturer for.
     */
    public function up(): void
    {
        Schema::create('product_document_analysis_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_document_analysis_id')
                ->constrained(indexName: 'document_analysis_findings_analysis_id_foreign')
                ->cascadeOnDelete();

            $table->string('severity');
            $table->string('category');
            $table->string('requirement');
            $table->text('rationale');
            $table->text('evidence')->nullable();
            $table->text('ask_manufacturer')->nullable();
            $table->unsignedSmallInteger('position');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_document_analysis_findings');
    }
};
