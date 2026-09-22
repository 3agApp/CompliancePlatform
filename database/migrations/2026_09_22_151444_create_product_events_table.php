<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Everything that has ever happened to a product, in the order it
     * happened: who classified it, who filled in what, which papers were
     * filed and by whom, and every step of every review round.
     *
     * Append only. Nothing in here is ever updated or deleted, because the
     * question it answers -- who changed this, and when -- is one that gets
     * asked long after the fact and usually by somebody outside the company.
     */
    public function up(): void
    {
        Schema::create('product_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            /**
             * Who did it, and which side they did it from. Both are kept as
             * ids and as the names they had at the time: an account can be
             * closed and an organization can be deleted, and neither may
             * take the record of what they did with it. The id is what links
             * the row to a live account; the snapshot is what the history
             * reads off once there is no longer one.
             */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_organization_name')->nullable();

            $table->string('type');

            /**
             * What the reviewer wrote when they sent the product back, which
             * is the whole point of sending it back rather than only
             * refusing it.
             */
            $table->text('note')->nullable();

            /**
             * What changed, as a map of column to its old and new value.
             * Stored rather than derived, because a diff that has to be
             * recomputed from the current row can only ever describe the
             * latest state, and this has to describe the moment.
             */
            $table->json('changes')->nullable();

            $table->timestamps();

            /**
             * How the history is read: one product's, newest first. The id
             * breaks the tie, because a batch written in one request shares
             * a timestamp to the second.
             */
            $table->index(['product_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_events');
    }
};
