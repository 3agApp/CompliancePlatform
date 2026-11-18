<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The countries a maker produces in, as ISO 3166-1 alpha-2 codes. A
     * product of the brand may only name one of them as its country of
     * origin. Empty means nobody has said, and then any country will do.
     */
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->json('countries')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('countries');
        });
    }
};
