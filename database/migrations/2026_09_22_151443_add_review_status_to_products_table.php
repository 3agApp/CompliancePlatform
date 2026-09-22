<?php

use App\Enums\ProductReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Whose move it is on a product: the distributor creates it, the
     * supplier fills it in and offers it up, and the distributor either
     * signs it off or sends it back with a note.
     *
     * The status is a column on the product rather than a read of its
     * history, because it is what the catalogue is filtered and sorted by:
     * the history says how it got here, and this says where it is. Every
     * product that already exists starts as a draft, which is what a product
     * nobody has submitted is.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('review_status')->default(ProductReviewStatus::Draft->value);

            /**
             * When it was last offered up and last ruled on. Both are of the
             * latest round only -- every earlier one is in the history --
             * and both are cleared when an edit withdraws the product,
             * because neither describes it any more.
             */
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            /**
             * How the distributor reads their catalogue once there is
             * anything in it worth reviewing: their own products, the ones
             * waiting on them first.
             */
            $table->index(['organization_id', 'review_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'review_status']);
            $table->dropColumn(['review_status', 'submitted_at', 'reviewed_at']);
        });
    }
};
