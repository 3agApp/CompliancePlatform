<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The column stays nullable at the database level for products that
     * predate supplier assignment. A supplier is required when a distributor
     * saves a product, which is enforced in the form request.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_connection_id')
                ->nullable()
                ->after('organization_id')
                ->constrained()
                ->nullOnDelete();

            /**
             * constrained() creates the foreign key but not an index, and
             * SQLite does not index foreign keys automatically. Without this
             * the supplier's product list is a full table scan.
             */
            $table->index('supplier_connection_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_connection_id']);
            $table->dropIndex(['supplier_connection_id']);
            $table->dropColumn('supplier_connection_id');
        });
    }
};
