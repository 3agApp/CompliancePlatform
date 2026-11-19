<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('a user with two factor enabled is challenged after entering their password', function () {
    User::factory()->withTwoFactor()->create(['email' => 'owner@example.com']);

    $page = visit(route('login'));

    $page->fill('email', 'owner@example.com')
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/two-factor-challenge')
        ->assertSee('Authentication code')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
});

test('the challenge swaps to the recovery code field and signs the user in', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Compliance', 'slug' => 'acme-compliance']);
    $user = User::factory()->withoutOrganization()->withTwoFactor()->create(['email' => 'owner@example.com']);
    $organization->members()->attach($user, ['role' => OrganizationRole::Owner->value]);
    $user->switchOrganization($organization);

    $page = visit(route('login'));

    $page->fill('email', 'owner@example.com')
        ->fill('password', 'password')
        ->click('@login-button')
        ->click('login using a recovery code')
        ->assertSee('Recovery code')
        ->fill('recovery_code', 'recovery-code-1')
        ->click('Continue')
        ->assertPathIs('/acme-compliance/dashboard')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticatedAs($user);
});
