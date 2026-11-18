<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('locale');
            $table->string('street')->nullable()->after('logo_path');
            $table->string('address_line_2')->nullable()->after('street');
            $table->string('postal_code', 20)->nullable()->after('address_line_2');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('country', 2)->nullable()->after('city');
            $table->string('map_url', 2048)->nullable()->after('country');
            $table->string('represented_by')->nullable()->after('map_url');
            $table->string('phone', 50)->nullable()->after('represented_by');
            $table->string('email')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
            $table->string('vat_number', 50)->nullable()->after('website');
            $table->json('production_countries')->nullable()->after('vat_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path', 'street', 'address_line_2', 'postal_code', 'city', 'country',
                'map_url', 'represented_by', 'phone', 'email', 'website',
                'vat_number', 'production_countries',
            ]);
        });
    }
};
