<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One gap an assessment found, with the reason it was flagged and what
     * to ask the manufacturer for.
     */
    public function up(): void
    {
        Schema::create('product_assessment_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_assessment_id')->constrained()->cascadeOnDelete();

            /**
             * The paper the finding is about, if it is about one. Null for a
             * gap in the product as a whole, and null once the paper itself
             * is removed -- the finding still says what was wrong with it.
             */
            $table->foreignId('product_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_name')->nullable();

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
        Schema::dropIfExists('product_assessment_findings');
    }
};
