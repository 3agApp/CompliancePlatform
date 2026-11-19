<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One AI reading of a single paper. Every run is kept, so a reviewer can
     * see what was said about the file before it was asked about again.
     */
    public function up(): void
    {
        Schema::create('product_document_analyses', function (Blueprint $table) {
            $table->id();

            /**
             * The reading belongs to the paper and goes with it: a reading of
             * a file no one can open any more has nothing left to point at.
             */
            $table->foreignId('product_document_id')->constrained()->cascadeOnDelete();

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

            $table->string('overall')->nullable();
            $table->text('summary')->nullable();

            /**
             * What the paper says about itself: who issued it, when, under
             * which standards, for which product.
             */
            $table->json('facts')->nullable();
            $table->string('failure_reason')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['product_document_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_document_analyses');
    }
};
