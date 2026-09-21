<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The legal families a distributor files its products under. Each
     * distributor keeps its own list: the names come from regulation and so
     * repeat across the platform, but the rows do not, and an organization
     * editing or deleting one of its own can never change what another
     * organization sees on its products.
     *
     * A new organization is given its starting families when it is created,
     * which is application behaviour rather than schema.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            /**
             * Also the index the organization's own list is read by, so no
             * separate index on organization_id is needed.
             */
            $table->unique(['organization_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
