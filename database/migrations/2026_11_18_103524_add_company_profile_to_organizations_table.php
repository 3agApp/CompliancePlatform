<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The company details an organization keeps about itself: where it is
     * based, how to reach it and who answers for it. A supplier's are what
     * its distributors read; a distributor's are what the public product
     * page shows as the importer's imprint. Every one is optional, because
     * an organization is useful long before anybody fills them in.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('street')->nullable()->after('locale');
            $table->string('postal_code', 20)->nullable()->after('street');
            $table->string('city')->nullable()->after('postal_code');
            $table->char('country', 2)->nullable()->after('city');
            $table->string('phone', 50)->nullable()->after('country');
            $table->string('email')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
            $table->string('vat_number', 50)->nullable()->after('website');
            $table->string('represented_by')->nullable()->after('vat_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'street', 'postal_code', 'city', 'country', 'phone',
                'email', 'website', 'vat_number', 'represented_by',
            ]);
        });
    }
};
