<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Where an imported file was fetched from, so the next import can tell
     * it already has it. Empty for everything a person uploaded.
     *
     * Not indexed: it is only ever compared within one product's handful of
     * files, and an index this wide would not fit MySQL's key length.
     */
    public function up(): void
    {
        Schema::table('product_documents', function (Blueprint $table) {
            $table->string('source_url', 2048)->nullable()->after('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_documents', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });
    }
};
