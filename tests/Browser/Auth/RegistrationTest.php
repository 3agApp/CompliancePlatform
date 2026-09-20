<?php

use App\Models\User;

test('a visitor registers through the form and is sent to onboarding', function () {
    $page = visit(route('register'));

    $page->fill('name', 'Test User')
        ->fill('email', 'test@example.com')
        ->fill('password', 'my-secret-password')
        ->fill('password_confirmation', 'my-secret-password')
        ->click('@register-user-button')
        ->assertPathIs('/onboarding')
        ->assertSee('Create your first organization')
        ->assertNoJavaScriptErrors();

    $user = User::where('email', 'test@example.com')->sole();

    expect($user->name)->toBe('Test User')
        ->and($user->current_organization_id)->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('the registration form shows the validation message when the passwords do not match', function () {
    $page = visit(route('register'));

    $page->fill('name', 'Test User')
        ->fill('email', 'test@example.com')
        ->fill('password', 'my-secret-password')
        ->fill('password_confirmation', 'a-different-password')
        ->click('@register-user-button')
        ->assertSee('The password field confirmation does not match.')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});
