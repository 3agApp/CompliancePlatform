<?php

use App\Enums\SupplierConnectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_connections', function (Blueprint $table) {
            $table->id();

            /**
             * Two public identifiers on purpose. "uuid" is what appears in the
             * distributor's own URL bar, while "code" is a bearer token mailed
             * to a single address. Collapsing them would leak a live claim
             * token to anyone the distributor shares their screen with.
             */
            $table->uuid()->unique();
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
             * becomes live at claim time, which is where the race is.
             */
            $table->unique(['distributor_organization_id', 'supplier_organization_id']);
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
