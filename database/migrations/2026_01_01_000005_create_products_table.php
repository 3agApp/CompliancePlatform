<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One product in a distributor's catalog, and the single place the
     * answers a market surveillance authority asks for are kept.
     *
     * The product is classified first -- who supplies it, which legal family
     * it falls under, which of that family's templates it is held to -- and
     * everything else is filled in afterwards, often by the other side of
     * the trade and rarely in one sitting. So only the classification and
     * the name are required here. Nothing a template asks for is enforced by
     * the schema: a product saves with every requirement unmet and keeps
     * score of what is still outstanding.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            /**
             * The trade, the family and the sheet are all three required: a
             * product nobody has classified is a product nobody can say
             * which rules it answers to, or who answers for it. Restricted
             * on delete, so none of them can be pulled out from under the
             * products still held to it -- the supplier, category and
             * template screens refuse that long before the database has to.
             *
             * A connection is ended by its status rather than by deleting
             * the row, so a revoked supplier keeps answering for what it
             * already supplied.
             */
            $table->foreignId('supplier_connection_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_template_id')->constrained()->restrictOnDelete();

            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');

            /**
             * A product is identified differently by each side of a trade --
             * the distributor knows it by their own article number, the
             * supplier by theirs -- and neither side can be made to fill in
             * the other's, so the record has to be savable with any subset
             * of these present.
             */
            $table->string('ean')->nullable();
            $table->string('internal_article_number')->nullable();
            $table->string('supplier_article_number')->nullable();
            $table->string('order_number')->nullable();

            /**
             * What the product is charged at the border, stored as the bare
             * digits it is made of, without the dots it is usually written
             * with, so that two people typing the same code the two usual
             * ways store one value.
             */
            $table->string('customs_tariff_number', 12)->nullable();

            $table->string('country_of_origin', 2)->nullable();

            /**
             * What the product claims about its own safety: what the package
             * must warn, who the product is for, what it is made of, how it
             * may and may not be used.
             *
             * None of it is indexed. A product is created long before anyone
             * knows its warnings, the supplier who fills them in may only
             * ever hold half of them, and none of this prose is ever looked
             * up by -- it is read off a product once you already have it.
             *
             * The age grading is a short marking -- "3+", "0-3 years", "14+"
             * -- rather than a paragraph, so it stays a string while its
             * neighbours are text.
             */
            $table->string('age_grading')->nullable();
            $table->text('safety_notice')->nullable();
            $table->text('warning_text')->nullable();
            $table->text('material_information')->nullable();
            $table->text('usage_restrictions')->nullable();
            $table->text('safety_instructions')->nullable();
            $table->text('additional_notes')->nullable();

            $table->timestamps();

            /**
             * How a catalog is read: one organization's products, in name
             * order or looked up by one of the numbers printed on the box.
             */
            $table->index(['organization_id', 'name']);
            $table->index(['organization_id', 'ean']);
            $table->index(['organization_id', 'internal_article_number']);
            $table->index(['organization_id', 'customs_tariff_number']);

            /**
             * constrained() creates the foreign key but not an index, and
             * SQLite does not index foreign keys automatically. Without
             * these the supplier's own product list is a full table scan.
             */
            $table->index('supplier_connection_id');
            $table->index('product_category_id');
            $table->index('brand_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
