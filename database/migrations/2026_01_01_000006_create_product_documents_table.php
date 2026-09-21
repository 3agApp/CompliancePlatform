<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The compliance fields on a product say what it claims; these are the
     * papers that prove it. A product carries as many as it needs and
     * several under the same heading -- a test report per component, a
     * certificate per standard -- so this is a table rather than a column
     * per kind.
     *
     * The file itself lives on the private disk and only its whereabouts are
     * recorded here. The name is kept beside the path because the path is a
     * hashed name nobody typed: the uploader gets their own filename back on
     * download, while nothing they typed ever reaches the filesystem.
     */
    public function up(): void
    {
        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            /**
             * A document outlives the person who filed it: the evidence is
             * what the authority asks for, not who clicked upload.
             */
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type');
            $table->string('name');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedInteger('size');
            $table->timestamps();

            /**
             * The only way the list is ever read: one product's papers,
             * gathered under their headings.
             */
            $table->index(['product_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_documents');
    }
};
