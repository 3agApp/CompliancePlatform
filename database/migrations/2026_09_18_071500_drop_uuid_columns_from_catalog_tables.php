<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The tables that carried a public uuid beside their key.
     *
     * @var array<int, string>
     */
    protected const array TABLES = [
        'products',
        'supplier_connections',
        'product_categories',
    ];

    /**
     * Run the migrations.
     *
     * These models are addressed by their auto-incrementing key from here on,
     * so the second identifier has nothing left to do. A supplier connection
     * keeps its "code": that is a bearer token mailed to one address, not a
     * name for the row, and collapsing the two would put a live claim token
     * in the distributor's URL bar.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique($table.'_uuid_unique');
                $blueprint->dropColumn('uuid');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * The column comes back nullable and is filled row by row before the
     * unique index goes on, because existing rows cannot all share one value
     * and the index would refuse them.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid()->nullable();
            });

            foreach (DB::table($table)->pluck('id') as $id) {
                DB::table($table)->where('id', $id)->update(['uuid' => Str::uuid()->toString()]);
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique('uuid');
            });
        }
    }
};
