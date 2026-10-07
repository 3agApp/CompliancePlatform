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
     * When the claim link was last mailed. Empty for a supplier the
     * distributor has added but not invited yet, so products can be filed
     * against them before anybody is told. Every row written before this
     * was invited the moment it was created, so that is when it was sent.
     */
    public function up(): void
    {
        Schema::table('supplier_connections', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('status');
        });

        DB::table('supplier_connections')->update(['invited_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_connections', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });
    }
};
