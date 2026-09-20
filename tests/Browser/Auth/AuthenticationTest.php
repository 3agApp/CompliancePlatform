<?php

use App\Models\Organization;
use App\Models\User;

test('a user signs in through the login form and lands on the dashboard of their organization', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Compliance']);
    $user = User::factory()->withoutOrganization()->create(['email' => 'owner@example.com']);
    $organization->members()->attach($user, ['role' => 'owner']);
    $user->switchOrganization($organization);

    $page = visit(route('login'));

    $page->fill('email', 'owner@example.com')
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs("/{$organization->slug}/dashboard")
        ->assertSee('Acme Compliance')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticatedAs($user);
});

test('the login form shows the validation message when the password is wrong', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    $page = visit(route('login'));

    $page->fill('email', 'owner@example.com')
        ->fill('password', 'not-the-password')
        ->click('@login-button')
        ->assertSee('These credentials do not match our records.')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
});

test('an unverified user is held at the verification prompt until they verify', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    visit(route('dashboard', $user->currentOrganization))
        ->assertPathIs('/email/verify')
        ->assertSee('Resend verification email')
        ->assertNoJavaScriptErrors();

    $user->markEmailAsVerified();

    visit(route('dashboard', $user->currentOrganization))
        ->assertPathIs("/{$user->currentOrganization->slug}/dashboard")
        ->assertNoJavaScriptErrors();
});
