<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The language the product's own safety texts are written in, so the
     * other languages can be kept beside them as translations. Every product
     * so far was written by its organization, so each one starts in its
     * organization's language.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('text_locale', 5)->default('en')->after('additional_notes');
        });

        DB::table('products')->update([
            'text_locale' => DB::raw('(select organizations.locale from organizations where organizations.id = products.organization_id)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('text_locale');
        });
    }
};
