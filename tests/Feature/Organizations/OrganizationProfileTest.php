<?php

use App\Enums\Country;
use App\Enums\OrganizationRole;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function companyProfile(array $overrides = []): array
{
    return [
        'street' => 'Tramstrasse 66',
        'address_line_2' => 'Im Walzwerk',
        'postal_code' => '4142',
        'city' => 'Münchenstein',
        'country' => 'CH',
        'map_url' => 'maps.app.goo.gl/abc123XYZ',
        'represented_by' => 'Stephanie Brenk',
        'phone' => '+41 61 683 47 57',
        'email' => 'info@example.ch',
        'website' => 'example.ch',
        'vat_number' => 'CHE-103.107.876',
        ...$overrides,
    ];
}

test('an owner can fill in the company profile', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)
        ->patch(route('organizations.profile.update', $organization), companyProfile())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('organizations.edit', $organization));

    expect($organization->fresh())
        ->street->toBe('Tramstrasse 66')
        ->city->toBe('Münchenstein')
        ->country->toBe(Country::Switzerland)
        ->represented_by->toBe('Stephanie Brenk')
        ->website->toBe('https://example.ch')
        ->map_url->toBe('https://maps.app.goo.gl/abc123XYZ')
        ->vat_number->toBe('CHE-103.107.876');
});

test('the edit page carries the company profile', function () {
    [$user, $organization] = newOrganizationMember(organizationAttributes: ['city' => 'Basel', 'country' => 'CH']);

    $this->actingAs($user)
        ->get(route('organizations.edit', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.city', 'Basel')
            ->where('profile.country', 'CH')
            ->where('profile.productionCountries', null)
            ->has('availableCountries', count(Country::cases())));
});

test('a member cannot change the company profile', function () {
    [$user, $organization] = newOrganizationMember(OrganizationRole::Member);

    $this->actingAs($user)
        ->patch(route('organizations.profile.update', $organization), companyProfile())
        ->assertForbidden();

    expect($organization->fresh()->city)->toBeNull();
});

test('the company profile is validated', function (string $field, mixed $value) {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)
        ->patch(route('organizations.profile.update', $organization), companyProfile([$field => $value]))
        ->assertSessionHasErrors($field);
})->with([
    'email' => ['email', 'not-an-address'],
    'website' => ['website', 'https://exa mple'],
    'country' => ['country', 'XX'],
    'phone' => ['phone', 'call me maybe'],
    'map link elsewhere' => ['map_url', 'https://www.openstreetmap.org/#map=17/47.5/7.6'],
    'map lookalike' => ['map_url', 'https://google.evil.example/maps/place/x'],
    'google but not maps' => ['map_url', 'https://www.google.com/search?q=3ag'],
]);

test('the usual shapes of a google maps link are accepted', function (string $link) {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)
        ->patch(route('organizations.profile.update', $organization), companyProfile(['map_url' => $link]))
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->map_url)->toBe($link);
})->with([
    'https://maps.app.goo.gl/abc123XYZ',
    'https://goo.gl/maps/abc123',
    'https://www.google.com/maps/place/Tramstrasse+66,+4142+M%C3%BCnchenstein',
    'https://www.google.ch/maps/@47.515,7.617,17z',
    'https://maps.google.co.uk/?q=47.515,7.617',
]);

test('a supplier can say which countries it produces in', function () {
    [$user, $organization] = newSupplierMember();

    $this->actingAs($user)
        ->patch(route('organizations.profile.update', $organization), companyProfile([
            'production_countries' => ['CN', 'VN'],
        ]))
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->allowedCountriesOfOrigin()->all())->toBe([Country::China, Country::Vietnam]);

    $this->actingAs($user)
        ->patch(route('organizations.profile.update', $organization), companyProfile([
            'production_countries' => [],
        ]))
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->allowedCountriesOfOrigin())->toBeNull();
});

test('a distributor cannot say which countries it produces in', function () {
    [$user, $organization] = newOrganizationMember();

    $this->actingAs($user)
        ->patch(route('organizations.profile.update', $organization), companyProfile([
            'production_countries' => ['CN'],
        ]))
        ->assertSessionHasErrors('production_countries');
});
