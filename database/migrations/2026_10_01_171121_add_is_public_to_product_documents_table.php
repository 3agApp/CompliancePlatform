<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Whether the distributor has released a document to the public page.
     * Off by default and set one document at a time: a test report or a
     * declaration of conformity can be worth showing a buyer, but nothing
     * filed against a product is public until somebody decides it is.
     */
    public function up(): void
    {
        Schema::table('product_documents', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_documents', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
