<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A run is read against the one before it, so a reviewer sees what the
     * factory fixed, what is new and what is still open. The model is shown
     * the earlier findings and says which of its own carry one on; the link
     * is kept here rather than worked out from wording, which differs from
     * one run to the next even when the gap is the same.
     */
    public function up(): void
    {
        Schema::table('product_assessments', function (Blueprint $table) {
            $table->foreignId('previous_assessment_id')->nullable()->after('requested_by')
                ->constrained('product_assessments')->nullOnDelete();
        });

        Schema::table('product_assessment_findings', function (Blueprint $table) {
            $table->foreignId('previous_finding_id')->nullable()->after('product_document_id')
                ->constrained('product_assessment_findings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_assessment_findings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_finding_id');
        });

        Schema::table('product_assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_assessment_id');
        });
    }
};
