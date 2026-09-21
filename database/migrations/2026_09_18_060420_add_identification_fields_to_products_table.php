<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every column is nullable. A product is identified differently by each
     * side of a trade -- the distributor knows it by their own article
     * number, the supplier by theirs -- and neither side can be made to fill
     * in the other's, so the record has to be savable with any subset of them
     * present.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            /**
             * Required: a product nobody has filed under a legal family is
             * a product nobody can say which rules it answers to.
             * Restricted on delete, so a family still in use cannot be
             * pulled out from under its products -- the category screen
             * refuses that long before the database has to.
             */
            $table->foreignId('product_category_id')
                ->after('brand')
                ->constrained()
                ->restrictOnDelete();
            $table->string('internal_article_number')->nullable()->after('ean');
            $table->string('supplier_article_number')->nullable()->after('internal_article_number');
            $table->string('order_number')->nullable()->after('supplier_article_number');

            /**
             * constrained() creates the foreign key but not an index, and
             * SQLite does not index foreign keys automatically.
             */
            $table->index('product_category_id');

            /**
             * The distributor's own article number is how they look a product
             * up, so it is indexed the same way the barcode is.
             */
            $table->index(['organization_id', 'internal_article_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'internal_article_number']);
            $table->dropForeign(['product_category_id']);
            $table->dropIndex(['product_category_id']);
            $table->dropColumn([
                'brand',
                'product_category_id',
                'internal_article_number',
                'supplier_article_number',
                'order_number',
            ]);
        });
    }
};
