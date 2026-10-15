<?php

use App\Enums\ImportRunStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One pass over the source's catalogue, and what came of it.
     */
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default(ImportRunStatus::Queued->value);

            $table->unsignedInteger('total')->nullable();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('ai_assigned')->default(0);

            /**
             * The rows that did not make it, and why. Capped by the model so
             * a catalogue that fails wholesale cannot grow this without end.
             */
            $table->json('failures')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
