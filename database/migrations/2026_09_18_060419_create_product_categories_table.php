<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The legal families a distributor starts with.
     *
     * Spelled out here rather than read from the model, so that a later
     * change to the defaults a new organization is given cannot silently
     * rewrite what this migration did.
     *
     * @var array<int, string>
     */
    protected const array INITIAL_NAMES = [
        'Toy',
        'Magnetic toy',
        'Filter',
    ];

    /**
     * Run the migrations.
     *
     * Each distributor keeps its own list of legal families. The names come
     * from regulation and so repeat across the platform, but the rows do not:
     * an organization editing or deleting one of its own can never change
     * what another organization sees on its products.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            /**
             * Also the index the organization's own list is read by, so no
             * separate index on organization_id is needed.
             */
            $table->unique(['organization_id', 'name']);
        });

        $this->giveExistingDistributorsTheDefaults();
    }

    /**
     * Hand the starting families to the distributors already on the platform.
     *
     * New organizations get theirs when they are created. Without this, every
     * organization that predates the table would be left with an empty list
     * and no products to file, which reads like a broken screen rather than
     * like a new feature.
     */
    protected function giveExistingDistributorsTheDefaults(): void
    {
        $organizationIds = DB::table('organizations')
            ->where('type', 'distributor')
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($organizationIds->isEmpty()) {
            return;
        }

        $now = now();

        DB::table('product_categories')->insert(
            $organizationIds
                ->crossJoin(self::INITIAL_NAMES)
                ->map(fn (array $pair) => [
                    'uuid' => Str::uuid()->toString(),
                    'organization_id' => $pair[0],
                    'name' => $pair[1],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
