<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A run reads either every paper of a product or one paper on its own.
     * Empty for the first; the document for the second. A run of one paper
     * goes with that paper: once it is deleted there is nothing left the
     * run was about, and it must not turn into a run of the whole product.
     */
    public function up(): void
    {
        Schema::table('product_assessments', function (Blueprint $table) {
            $table->foreignId('product_document_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_documents')
                ->cascadeOnDelete();

            $table->index(['product_id', 'product_document_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_assessments', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'product_document_id', 'status']);
            $table->dropConstrainedForeignId('product_document_id');
        });
    }
};
