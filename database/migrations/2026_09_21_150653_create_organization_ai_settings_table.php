<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every organization brings its own AI provider and its own key: the
     * platform ships none, and nobody's usage is billed to anybody else.
     *
     * A table of its own rather than columns on organizations, because the
     * current organization is shared with every Inertia response. A secret
     * on that model would be one careless serialization away from the page
     * source; here it has to be asked for by name.
     */
    public function up(): void
    {
        Schema::create('organization_ai_settings', function (Blueprint $table) {
            $table->id();

            /**
             * One setting per organization. Changing provider replaces what
             * is there rather than adding beside it.
             */
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('provider');
            $table->string('model');

            /**
             * Encrypted by the model's cast, so this holds ciphertext and is
             * far too long for a string column. It is never selected into a
             * response: the page is only ever shown the last few characters.
             */
            $table->text('api_key');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_ai_settings');
    }
};
