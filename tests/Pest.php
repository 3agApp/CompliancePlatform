<?php

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Organization;
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
