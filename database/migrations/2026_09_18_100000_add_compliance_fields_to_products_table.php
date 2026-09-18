<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * What a product is called and how it is numbered says nothing about
     * whether it may be placed on the market. These are the answers a market
     * surveillance authority asks for: what the package must warn, who the
     * product is for, what it is made of, how it may and may not be used.
     *
     * Every one is nullable and none is indexed. A product is created long
     * before anyone knows its warnings, the supplier who fills them in may
     * only ever hold half of them, and none of this prose is ever looked up
     * by -- it is read off a product once you already have it.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            /**
             * A short marking -- "3+", "0-3 years", "14+" -- rather than a
             * paragraph, so it stays a string while its neighbours are text.
             */
            $table->string('age_grading')->nullable()->after('country_of_origin');

            $table->text('safety_notice')->nullable()->after('age_grading');
            $table->text('warning_text')->nullable()->after('safety_notice');
            $table->text('material_information')->nullable()->after('warning_text');
            $table->text('usage_restrictions')->nullable()->after('material_information');
            $table->text('safety_instructions')->nullable()->after('usage_restrictions');
            $table->text('additional_notes')->nullable()->after('safety_instructions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'age_grading',
                'safety_notice',
                'warning_text',
                'material_information',
                'usage_restrictions',
                'safety_instructions',
                'additional_notes',
            ]);
        });
    }
};
