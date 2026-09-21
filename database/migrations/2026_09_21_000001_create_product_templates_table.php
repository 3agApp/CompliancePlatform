<?php

use App\Enums\ProductRequirement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A template is the homework sheet for one legal family: which papers
     * and which fields a product of that kind is expected to carry. Several
     * can sit under the same family, because a distributor may hold the same
     * kind of product to different standards depending on where it is sold.
     *
     * Each requirement is its own boolean column rather than a blob, so the
     * schema says out loud what a template can ask for and a mistyped key is
     * a migration error rather than a silently ignored flag. The names come
     * from the requirement register, which is the only place they are
     * spelled out.
     *
     * Nothing here is ever enforced against a product. A product saves with
     * every one of them unmet; they drive a checklist, not validation.
     */
    public function up(): void
    {
        Schema::create('product_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            foreach (ProductRequirement::columns() as $column) {
                $table->boolean($column)->default(false);
            }

            $table->timestamps();

            /**
             * Also the index a category's own list is read by, so no
             * separate index on product_category_id is needed. A template
             * belongs to an organization only through its category, which is
             * where the tenancy of the whole tree already sits.
             */
            $table->unique(['product_category_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_templates');
    }
};
