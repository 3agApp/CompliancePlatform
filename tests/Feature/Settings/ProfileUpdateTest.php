<?php

use App\Enums\OrganizationRole;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('the profile page shows the identity accounts holds, read only', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@3ag.local']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.user.name', 'Ada Lovelace')
            ->where('auth.user.email', 'ada@3ag.local'),
        );
});

test('there is no route left to edit the profile with', function () {
    expect(Route::has('profile.update'))->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->patch('/settings/profile', ['name' => 'Someone Else', 'email' => 'someone-else@3ag.local'])
        ->assertMethodNotAllowed();
});

test('the email address a user signs in with cannot be changed from here', function () {
    $user = User::factory()->create(['email' => 'ada@3ag.local']);

    $this->actingAs($user)
        ->post('/settings/profile', ['name' => 'Someone Else', 'email' => 'victim@3ag.local'])
        ->assertMethodNotAllowed();

    expect($user->fresh()->email)->toBe('ada@3ag.local');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'confirmation' => 'DELETE',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('the confirmation word must be typed to delete the account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'confirmation' => 'delete my account',
        ]);

    $response
        ->assertSessionHasErrors('confirmation')
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
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
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
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertRedirect('/');

    expect($organization->fresh()->owner()?->id)->toBe($first->id);
});

test('deleting an account winds up an organization nobody else is in', function () {
    [$owner, $organization] = newOrganizationMember();

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertRedirect('/');

    expect(Organization::withTrashed()->find($organization->id)->trashed())->toBeTrue();
});

test('winding up a distributor revokes the connections its suppliers held', function () {
    [$owner, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    expect($connection->fresh()->status)->toBe(SupplierConnectionStatus::Active);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
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
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertRedirect('/');

    expect($organization->fresh()->owner()?->id)->toBe($owner->id)
        ->and($organization->fresh()->members()->count())->toBe(1);
});

test('no organization is left without an owner when its owner goes', function () {
    [$owner, $organization] = newOrganizationMember();

    $member = User::factory()->withoutOrganization()->create();
    $organization->memberships()->create(['user_id' => $member->id, 'role' => OrganizationRole::Member]);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['confirmation' => 'DELETE'])
        ->assertRedirect('/');

    expect($organization->fresh()->trashed())->toBeFalse()
        ->and($organization->fresh()->owner())->not->toBeNull();
});
