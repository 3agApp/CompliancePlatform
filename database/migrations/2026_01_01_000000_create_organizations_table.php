<?php

use App\Enums\OrganizationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An organization is one company on the platform, and every company is
     * one of the two sides of a trade: a distributor placing products on the
     * market, or a supplier answering for them. The type decides what the
     * app shows and what it lets through, so it is a column rather than
     * something inferred from what a company happens to own.
     *
     * Deleting an organization deletes it. The foreign keys below carry the
     * memberships, invitations, suppliers and settings away with it, and
     * App\Actions\Organizations\DeleteOrganization clears what the database
     * cannot -- the products, which other tables hold back with restricts,
     * and the document files, which no cascade can reach.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default(OrganizationType::Distributor->value);
            $table->timestamps();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();

            /**
             * A bearer token mailed to one address: whoever holds it joins
             * the organization, so it is the row's only public name.
             */
            $table->string('code', 64)->unique();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        /**
         * Which of their organizations a user is currently looking at. It
         * lives on the user rather than in the session so that a person
         * comes back to the company they left off in, on whatever device.
         * Cleared rather than cascaded: losing an organization must not
         * take the account with it.
         */
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')
                ->nullable()
                ->after('password')
                ->constrained('organizations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_organization_id');
        });

        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
