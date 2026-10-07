<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reading a document's contents means sending the file to the
     * organization's provider, which naming its kind never did. That is a
     * different promise about where a supplier's papers go, so it is one an
     * organization makes on purpose rather than one that comes with the key.
     */
    public function up(): void
    {
        Schema::table('organization_ai_settings', function (Blueprint $table) {
            $table->boolean('allow_document_analysis')->default(false)->after('api_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_ai_settings', function (Blueprint $table) {
            $table->dropColumn('allow_document_analysis');
        });
    }
};
