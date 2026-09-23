<?php

use App\Enums\SupplierConnectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One trading relationship: a distributor on one side, and on the other
     * a supplier who may not have joined the platform yet. The row is
     * created when the invitation is sent and the supplier side stays null
     * until somebody claims it, which is why every product and every brand
     * hangs off the connection rather than off a supplier organization.
     */
    public function up(): void
    {
        Schema::create('supplier_connections', function (Blueprint $table) {
            $table->id();

            /**
             * A bearer token mailed to a single address, kept apart from the
             * row's key: the id is what the distributor sees in their own URL
             * bar, and collapsing the two would put a live claim token there.
             */
            $table->string('code', 64)->unique();

            $table->foreignId('distributor_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('supplier_organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            $table->string('company_name');
            $table->string('contact_email');
            $table->string('status')->default(SupplierConnectionStatus::Pending->value);

            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            /**
             * A distributor and a supplier organization share exactly one row
             * forever. The constraint is inert while the supplier side is null
             * (a distributor may have several invitations outstanding) and
             * becomes live at claim time, which is where the race is. Named
             * by hand: the generated name exceeds MySQL's 64-character limit.
             */
            $table->unique(['distributor_organization_id', 'supplier_organization_id'], 'supplier_connections_distributor_supplier_unique');
            $table->index(['supplier_organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_connections');
    }
};
