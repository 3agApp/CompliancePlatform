<?php

use App\Enums\AiProvider;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\Organizations\OrganizationInvitation as OrganizationInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->owner, ['role' => OrganizationRole::Owner->value]);
});

test('a healthy organization has nothing that needs attention', function () {
    $this->actingAs($this->owner)
        ->get(route('organizations.edit', $this->organization))
        ->assertInertia(fn (Assert $page) => $page->where('attention', []));
});

test('an AI key that can no longer be decrypted needs attention', function () {
    $this->organization->aiSetting()->create([
        'provider' => AiProvider::Gemini->value,
        'model' => AiProvider::Gemini->defaultModel(),
        'api_key' => 'a-key-that-works',
    ]);

    DB::table('organization_ai_settings')->update(['api_key' => 'not-encrypted-with-this-app-key']);

    $this->actingAs($this->owner)
        ->get(route('organizations.edit', $this->organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attention.0.key', 'ai-key-unreadable')
            ->where('attention.0.target', 'ai-provider'));
});

test('an invitation can be resent with a fresh expiry', function () {
    Notification::fake();

    $invitation = OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $this->organization->id,
        'invited_by' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->post(route('organizations.invitations.resend', [$this->organization, $invitation]))
        ->assertRedirect(route('organizations.edit', $this->organization));

    expect($invitation->fresh()->isExpired())->toBeFalse();

    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
});

test('a member cannot resend an invitation', function () {
    Notification::fake();

    $member = User::factory()->create();
    $this->organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $this->organization->id,
        'invited_by' => $this->owner->id,
    ]);

    $this->actingAs($member)
        ->post(route('organizations.invitations.resend', [$this->organization, $invitation]))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('an invitation from another organization cannot be resent through this one', function () {
    $invitation = OrganizationInvitation::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('organizations.invitations.resend', [$this->organization, $invitation]))
        ->assertNotFound();
});

test('expired invitations need attention and are marked expired', function () {
    OrganizationInvitation::factory()->expired()->create([
        'organization_id' => $this->organization->id,
        'invited_by' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('organizations.edit', $this->organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attention', fn ($items) => collect($items)->pluck('key')->contains('expired-invitations'))
            ->where('invitations.0.is_expired', true));
});

test('the old appearance address leads to the profile page', function () {
    $this->actingAs($this->owner)
        ->get(route('appearance.edit'))
        ->assertRedirect('/settings/profile#appearance');
});
