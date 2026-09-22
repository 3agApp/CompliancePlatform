<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The seal a distributor has set on a product by hand, standing in front
     * of the one its review earned.
     *
     * Null is the ordinary case and means the seal is read off the review.
     * An override is somebody's word rather than the outcome of a check, so
     * it is never stored on its own: who gave it and why are part of the
     * same fact, and the public page says a hand-set seal is hand-set.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('seal_override')->nullable();
            $table->text('seal_override_reason')->nullable();
            $table->foreignId('seal_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('seal_overridden_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seal_overridden_by');
            $table->dropColumn(['seal_override', 'seal_override_reason', 'seal_overridden_at']);
        });
    }
};
