<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * What a source system knows about a product that this platform did not ask for
     * until now: whether it is still being sold, and the copy it is sold
     * with. Every existing product is on sale as far as anybody here knows,
     * so the flag starts true.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
            $table->text('short_description')->nullable()->after('is_active');
            $table->text('long_description')->nullable()->after('short_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'short_description', 'long_description']);
        });
    }
};
