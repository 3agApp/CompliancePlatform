<?php

use App\Enums\OrganizationRole;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
test('deleting an account hands its organizations to the longest standing admin', function () {
    [$owner, $organization] = newOrganizationMember();

    $newestAdmin = User::factory()->withoutOrganization()->create();
    $oldestAdmin = User::factory()->withoutOrganization()->create();
    $member = User::factory()->withoutOrganization()->create();

    // Created out of order, so the test proves it picks by standing rather
    // than by whichever row happens to come back first.
    $organization->memberships()->create(['user_id' => $member->id, 'role' => OrganizationRole::Member]);
    $organization->memberships()->create(['user_id' => $newestAdmin->id, 'role' => OrganizationRole::Admin])
        ->forceFill(['created_at' => now()->addDay()])->save();
    $organization->memberships()->create(['user_id' => $oldestAdmin->id, 'role' => OrganizationRole::Admin])
        ->forceFill(['created_at' => now()->subDay()])->save();

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect($organization->fresh()->owner()?->id)->toBe($oldestAdmin->id)
        ->and($oldestAdmin->fresh()->ownsOrganization($organization))->toBeTrue();
});

test('deleting an account falls back to the longest standing member', function () {
    [$owner, $organization] = newOrganizationMember();

    $first = User::factory()->withoutOrganization()->create();
    $second = User::factory()->withoutOrganization()->create();

    $organization->memberships()->create(['user_id' => $second->id, 'role' => OrganizationRole::Member])
        ->forceFill(['created_at' => now()->addDay()])->save();
    $organization->memberships()->create(['user_id' => $first->id, 'role' => OrganizationRole::Member])
        ->forceFill(['created_at' => now()->subDay()])->save();

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect($organization->fresh()->owner()?->id)->toBe($first->id);
});

test('deleting an account winds up an organization nobody else is in', function () {
    [$owner, $organization] = newOrganizationMember();

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect(Organization::withTrashed()->find($organization->id)->trashed())->toBeTrue();
});

test('winding up a distributor revokes the connections its suppliers held', function () {
    [$owner, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    expect($connection->fresh()->status)->toBe(SupplierConnectionStatus::Active);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    // The distributor is only soft deleted, so nothing cascades. Left live,
    // the connection would keep a supplier reading and writing products
    // belonging to an organization that no longer exists.
    expect($connection->fresh()->status)->toBe(SupplierConnectionStatus::Revoked);
});

test('deleting an account leaves organizations it only belonged to alone', function () {
    [$owner, $organization] = newOrganizationMember();

    $member = User::factory()->withoutOrganization()->create();
    $organization->memberships()->create(['user_id' => $member->id, 'role' => OrganizationRole::Member]);

    $this->actingAs($member)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect($organization->fresh()->owner()?->id)->toBe($owner->id)
        ->and($organization->fresh()->members()->count())->toBe(1);
});

test('no organization is left without an owner when its owner goes', function () {
    [$owner, $organization] = newOrganizationMember();

    $member = User::factory()->withoutOrganization()->create();
    $organization->memberships()->create(['user_id' => $member->id, 'role' => OrganizationRole::Member]);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect($organization->fresh()->trashed())->toBeFalse()
        ->and($organization->fresh()->owner())->not->toBeNull();
});
