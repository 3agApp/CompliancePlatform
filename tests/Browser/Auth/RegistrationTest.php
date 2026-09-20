<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

test('a visitor registers, verifies their address and is then sent to onboarding', function () {
    $page = visit(route('register'));

    // Registration signs the account in but leaves it unverified, so the
    // verification prompt comes before anything else.
    $page->fill('name', 'Test User')
        ->fill('email', 'test@example.com')
        ->fill('password', 'my-secret-password')
        ->fill('password_confirmation', 'my-secret-password')
        ->click('@register-user-button')
        ->assertPathIs('/email/verify')
        ->assertSee('Resend verification email')
        ->assertNoJavaScriptErrors();

    $user = User::where('email', 'test@example.com')->sole();

    expect($user->name)->toBe('Test User')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->current_organization_id)->toBeNull();

    $this->assertAuthenticatedAs($user);

    // The link the verification mail carries.
    visit(URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]))
        ->assertPathIs('/onboarding')
        ->assertSee('Create your first organization')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
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
