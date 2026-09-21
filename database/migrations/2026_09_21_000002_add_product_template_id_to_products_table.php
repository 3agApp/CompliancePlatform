<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every product names the template it is held to, and the template's
     * category is the product's category -- a pairing the schema cannot
     * state on its own, so the form request carries it.
     *
     * Required, because a product nobody has classified is a product nobody
     * can say anything about. Restricted on delete rather than cascaded: a
     * template in use is a template whose products would otherwise lose
     * their sheet without anyone deciding they should.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_template_id')
                ->after('product_category_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_template_id');
        });
    }
};
