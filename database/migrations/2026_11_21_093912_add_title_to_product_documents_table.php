<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The name a document is shown under, chosen by whoever filed it. The
     * uploaded filename stays in `name` for the team; the public page shows
     * this instead, or the kind of document when it is empty.
     */
    public function up(): void
    {
        Schema::table('product_documents', function (Blueprint $table) {
            $table->string('title')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_documents', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
