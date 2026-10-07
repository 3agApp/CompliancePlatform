<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One AI reading of a product's papers. Every run is kept, so a reviewer
     * can see what was flagged before and why, and so the reading that sat
     * behind a decision is still there after the documents have changed.
     */
    public function up(): void
    {
        Schema::create('product_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            /**
             * Whose provider was asked, and who asked. The person may leave;
             * the run stays.
             */
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status');
            $table->string('provider');
            $table->string('model');
            $table->string('prompt_version');

            /**
             * The papers as they were when the run was asked for. Documents
             * are replaced and removed, so the run carries its own list of
             * what it read and what it could not.
             */
            $table->json('documents');
            $table->json('skipped_documents');

            $table->string('overall')->nullable();
            $table->text('summary')->nullable();
            $table->text('factory_request')->nullable();
            $table->string('failure_reason')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_assessments');
    }
};
