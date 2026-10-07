<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An organization speaks one language by default, and anybody in it may
     * choose another for themselves. A person's choice is empty until they
     * make one, which is what lets them keep following the organization's
     * default when it changes.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
