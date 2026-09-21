<?php

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\ProductCategory;
use App\Models\ProductTemplate;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a user that belongs to a fresh organization with the given role,
 * with that organization already selected as their current one.
 *
 * @param  array<string, mixed>  $organizationAttributes
 * @return array{0: User, 1: Organization}
 */
function newOrganizationMember(OrganizationRole $role = OrganizationRole::Owner, array $organizationAttributes = []): array
{
    $user = User::factory()->withoutOrganization()->create();
    $organization = Organization::factory()->create($organizationAttributes);

    $organization->members()->attach($user, ['role' => $role->value]);
    $user->switchOrganization($organization);

    return [$user, $organization];
}

/**
 * Create a user that owns a supplier organization.
 *
 * @return array{0: User, 1: Organization}
 */
function newSupplierMember(OrganizationRole $role = OrganizationRole::Owner): array
{
    return newOrganizationMember($role, ['type' => OrganizationType::Supplier]);
}

/**
 * Create a connection from the given distributor.
 *
 * Passing a supplier organization creates a connection that has already been
 * claimed and is live; leaving it out creates one still waiting to be claimed.
 *
 * @param  array<string, mixed>  $attributes
 */
function newSupplierConnection(Organization $distributor, ?Organization $supplier = null, array $attributes = []): SupplierConnection
{
    $factory = SupplierConnection::factory()->for($distributor, 'distributorOrganization');

    if ($supplier instanceof Organization) {
        $factory = $factory->active($supplier);
    }

    return $factory->create($attributes);
}

/**
 * Get one of the legal families an organization is created with.
 *
 * The list is per organization, so the lookup has to be too: every
 * distributor on the platform has a row called "Toy" and they are not
 * interchangeable.
 */
function legalFamily(Organization $organization, string $name = 'Toy'): ProductCategory
{
    return $organization->productCategories()->where('name', $name)->sole();
}

/**
 * Get a template under one of an organization's legal families, adding it
 * the first time it is asked for.
 *
 * Organizations start with the families but with no templates: what a
 * distributor holds a kind of product to is their own reading, so there is
 * nothing sensible to hand them. Every product names one all the same, so
 * every write test needs this.
 */
function familyTemplate(Organization $organization, string $family = 'Toy', string $name = 'Standard'): ProductTemplate
{
    return legalFamily($organization, $family)->templates()->firstOrCreate(['name' => $name]);
}

/**
 * Get a maker named under one trade, adding it the first time it is asked
 * for.
 *
 * A brand hangs off the supplier connection rather than off an
 * organization, so a test that wants to put one on a product needs the
 * trade that product sits on. Unlike the legal families nothing is created
 * with the organization, so it has to be named first.
 */
function carriedBrand(SupplierConnection $connection, string $name = 'Alpro'): Brand
{
    return $connection->brands()->firstOrCreate(['name' => $name]);
}
