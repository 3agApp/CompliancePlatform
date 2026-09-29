<?php

use App\Enums\OrganizationRole;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\Organizations\OrganizationInvitation as OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;

test('an invitation is sent through the invite member dialog', function () {
    Notification::fake();

    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user);

    $page = visit(route('organizations.edit', ['organization' => $organization->slug]));

    $page->click('@invite-member-button')
        ->assertSee('Invite an organization member')
        ->fill('@invite-email', 'colleague@example.com')
        ->click('@invite-role')
        ->click('[role="option"]:has-text("Admin")')
        ->click('@invite-submit')
        ->assertDontSee('Invite an organization member')
        ->assertSee('Invitation sent.')
        ->assertSee('colleague@example.com')
        ->assertNoJavaScriptErrors();

    expect(OrganizationInvitation::sole())
        ->email->toBe('colleague@example.com')
        ->role->toBe(OrganizationRole::Admin)
        ->organization_id->toBe($organization->id);

    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
});

test('the invite member dialog shows the validation message for an email that was already invited', function () {
    Notification::fake();

    [$user, $organization] = newOrganizationMember();
    OrganizationInvitation::factory()->for($organization)->create(['email' => 'colleague@example.com']);

    $this->actingAs($user);

    $page = visit(route('organizations.edit', ['organization' => $organization->slug]));

    $page->click('@invite-member-button')
        ->fill('@invite-email', 'colleague@example.com')
        ->click('@invite-submit')
        ->assertSee('Invite an organization member')
        ->assertSee('An invitation has already been sent to this email address.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('organization_invitations', 1);
    Notification::assertNothingSent();
});

test('a pending invitation is cancelled through the confirmation dialog', function () {
    [$user, $organization] = newOrganizationMember();
    $invitation = OrganizationInvitation::factory()->for($organization)->create(['email' => 'colleague@example.com']);

    $this->actingAs($user);

    $page = visit(route('organizations.edit', ['organization' => $organization->slug]));

    $page->click('@invitation-cancel-button')
        ->assertSee('Cancel invitation')
        ->click('@cancel-invitation-confirm')
        ->assertSee('Invitation cancelled.')
        ->assertDontSee('colleague@example.com')
        ->assertNoJavaScriptErrors();

    $this->assertModelMissing($invitation);
});

test('the role of a member is changed through the role dropdown, after confirming', function () {
    [$owner, $organization] = newOrganizationMember();
    $member = User::factory()->withoutOrganization()->create(['name' => 'Jordan Lee']);
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($owner);

    $page = visit(route('organizations.edit', ['organization' => $organization->slug]));

    $page->click('@member-role-trigger')
        ->click('[role="menuitemradio"]:has-text("Admin")')
        ->assertSee('Change role')
        ->click('@member-role-confirm')
        ->assertSee('Member role updated.')
        ->assertNoJavaScriptErrors();

    expect($organization->members()->where('user_id', $member->id)->first()->pivot->role)
        ->toBe(OrganizationRole::Admin);
});

test('a member is removed through the confirmation dialog', function () {
    [$owner, $organization] = newOrganizationMember();
    $member = User::factory()->withoutOrganization()->create(['name' => 'Jordan Lee']);
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($owner);

    $page = visit(route('organizations.edit', ['organization' => $organization->slug]));

    $page->click('@member-remove-button')
        ->assertSee('Remove organization member')
        ->click('@remove-member-confirm')
        ->assertSee('Member removed.')
        ->assertDontSee('Jordan Lee')
        ->assertNoJavaScriptErrors();

    expect($organization->fresh()->members()->whereKey($member->id)->exists())->toBeFalse();
});
