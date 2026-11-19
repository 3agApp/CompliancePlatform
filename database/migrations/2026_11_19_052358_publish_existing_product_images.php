<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Until now the public page showed every product image whatever its
     * switch said. It now follows the switch, so the images already on show
     * are switched on first: nothing a buyer can see today disappears on
     * deploy. Images filed from here on start hidden, like any document.
     */
    public function up(): void
    {
        /**
         * One plain statement rather than a query builder update: compiling
         * an update for MySQL asks the server for its version, which the
         * migration test's pretend run has no server to answer.
         */
        DB::statement("update product_documents set is_public = 1 where type = 'product_image'");
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: which images were hidden before cannot be told apart
     * from the ones this switched on, and the old page ignored the switch.
     */
    public function down(): void
    {
        //
    }
};
