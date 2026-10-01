<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One print run of serialised labels: "two hundred of these, for the
     * shipment going out on Friday". Kept as a row of its own so a run can
     * be printed again after a jammed roll, and withdrawn as a whole when a
     * roll goes missing.
     */
    public function up(): void
    {
        Schema::create('label_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');

            /**
             * Who or what the run was printed for -- a customer, a shipment,
             * an order -- so a code that turns up checked a hundred times
             * can be traced back to where its roll went.
             */
            $table->string('issued_for');
            $table->text('note')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_batches');
    }
};
