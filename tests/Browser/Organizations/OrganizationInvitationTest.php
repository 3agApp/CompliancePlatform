<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;

test('an invited user accepts an invitation and lands on the dashboard of that organization', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Compliance', 'slug' => 'acme-compliance']);
    $inviter = User::factory()->withoutOrganization()->create(['name' => 'Alex Rivera']);
    $organization->members()->attach($inviter, ['role' => OrganizationRole::Owner->value]);

    $user = User::factory()->withoutOrganization()->create(['email' => 'invited@example.com']);
    $invitation = OrganizationInvitation::factory()->for($organization)->create([
        'email' => 'invited@example.com',
        'invited_by' => $inviter->id,
        'role' => OrganizationRole::Admin,
    ]);

    $this->actingAs($user);

    $page = visit(route('invitations.index'));

    $page->assertSee('Alex Rivera invited you to join as Admin.')
        ->click('@pending-invitation-accept')
        ->assertPathIs('/acme-compliance/dashboard')
        ->assertSee('Invitation accepted.')
        ->assertNoJavaScriptErrors();

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    expect($organization->members()->where('user_id', $user->id)->first()->pivot->role)
        ->toBe(OrganizationRole::Admin);
    expect($user->fresh()->currentOrganization->name)->toBe('Acme Compliance');
});

test('an invited user without an organization declines an invitation and returns to onboarding', function () {
    $organization = Organization::factory()->create();
    $inviter = User::factory()->withoutOrganization()->create();
    $organization->members()->attach($inviter, ['role' => OrganizationRole::Owner->value]);

    $user = User::factory()->withoutOrganization()->create(['email' => 'invited@example.com']);
    $invitation = OrganizationInvitation::factory()->for($organization)->create([
        'email' => 'invited@example.com',
        'invited_by' => $inviter->id,
    ]);

    $this->actingAs($user);

    $page = visit(route('invitations.index'));

    $page->click('@pending-invitation-decline')
        ->assertPathIs('/onboarding')
        ->assertSee('Invitation declined.')
        ->assertNoJavaScriptErrors();

    $this->assertModelMissing($invitation);
    expect($organization->members()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('the invitations page shows the empty state when nothing is waiting', function () {
    [$user] = newOrganizationMember();

    $this->actingAs($user);

    visit(route('invitations.index'))
        ->assertSee('No pending invitations')
        ->assertNoJavaScriptErrors();
});
