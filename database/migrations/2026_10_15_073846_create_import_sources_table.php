<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The source a distributor imports its catalogue from, and how. A table of
     * its own rather than columns on organizations for the same reason as
     * the AI settings: the key must never be one serialization away from a
     * page.
     */
    public function up(): void
    {
        Schema::create('import_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('provider');
            $table->string('api_url', 2048);

            /**
             * Encrypted by the model's cast, so this holds ciphertext.
             */
            $table->text('api_key');

            /**
             * The source names images by file name only; this is put in front.
             */
            $table->string('file_url_prefix', 2048)->nullable();

            $table->string('match_key');
            $table->string('on_existing');

            /**
             * The template a product is filed under when neither its
             * manufacturer nor the AI says otherwise.
             */
            $table->foreignId('default_product_template_id')->nullable()->constrained('product_templates')->nullOnDelete();
            $table->boolean('use_ai_for_templates')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_sources');
    }
};
