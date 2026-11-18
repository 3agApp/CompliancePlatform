<?php

use App\Actions\Organizations\DeleteOrganization;
use App\Enums\OrganizationRole;
use App\Models\ProductDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(ProductDocument::DISK);
});

test('an owner can upload a logo, and it is served in public', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)
        ->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('organizations.edit', $organization));

    $organization->refresh();

    Storage::disk(ProductDocument::DISK)->assertExists($organization->logo_path);
    expect($organization->logo_path)->toStartWith('organization-logos/'.$organization->id.'/')
        ->and($organization->logoUrl())->toContain('/o/'.$organization->slug.'/logo');

    auth()->logout();

    $this->get($organization->logoUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('a new logo replaces the old file', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)->post(route('organizations.logo.store', $organization), [
        'logo' => UploadedFile::fake()->image('first.png', 100, 100),
    ]);
    $first = $organization->fresh()->logo_path;

    $this->actingAs($user)->post(route('organizations.logo.store', $organization), [
        'logo' => UploadedFile::fake()->image('second.jpg', 100, 100),
    ]);

    Storage::disk(ProductDocument::DISK)->assertMissing($first);
    Storage::disk(ProductDocument::DISK)->assertExists($organization->fresh()->logo_path);
});

test('a logo can be removed', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)->post(route('organizations.logo.store', $organization), [
        'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
    ]);
    $path = $organization->fresh()->logo_path;

    $this->actingAs($user)
        ->delete(route('organizations.logo.destroy', $organization))
        ->assertRedirect(route('organizations.edit', $organization));

    Storage::disk(ProductDocument::DISK)->assertMissing($path);
    expect($organization->fresh()->logo_path)->toBeNull();

    $this->get(route('organizations.logo', $organization))->assertNotFound();
});

test('a logo must be a raster image of a sensible size', function (UploadedFile $file) {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)
        ->post(route('organizations.logo.store', $organization), ['logo' => $file])
        ->assertSessionHasErrors('logo');

    expect($organization->fresh()->logo_path)->toBeNull();
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
    'pdf' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
    'too small' => fn () => UploadedFile::fake()->image('logo.png', 20, 20),
    'too heavy' => fn () => UploadedFile::fake()->image('logo.png', 200, 200)->size(3000),
]);

test('a member cannot change the logo', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);

    $this->actingAs($user)
        ->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
        ])
        ->assertForbidden();

    expect($organization->fresh()->logo_path)->toBeNull();
});

test('deleting an organization takes its logo with it', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)->post(route('organizations.logo.store', $organization), [
        'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
    ]);
    $path = $organization->fresh()->logo_path;

    app(DeleteOrganization::class)->handle($organization->fresh());

    Storage::disk(ProductDocument::DISK)->assertMissing($path);
});
