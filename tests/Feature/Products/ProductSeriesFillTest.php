<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Enums\ProductRequirement;
use App\Enums\ProductReviewStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A distributor with a series of products from a supplier that has been
 * invited and not joined, on a template that asks for the toy basics --
 * the shape production is in.
 *
 * @return array{0: User, 1: Organization, 2: ProductTemplate}
 */
function distributorWithSeries(int $products = 3, OrganizationRole $role = OrganizationRole::Owner): array
{
    [$user, $organization] = newOrganizationMember($role);
    $connection = newSupplierConnection($organization);

    $template = ProductTemplate::factory()
        ->for(legalFamily($organization), 'category')
        ->requiring(
            ProductRequirement::DeclarationOfConformity,
            ProductRequirement::AgeGrading,
            ProductRequirement::WarningText,
            ProductRequirement::CountryOfOrigin,
        )
        ->create();

    Product::factory()->for($organization)->count($products)->create([
        'supplier_connection_id' => $connection->id,
        'product_category_id' => $template->product_category_id,
        'product_template_id' => $template->id,
    ]);

    return [$user, $organization, $template];
}

/**
 * Send one bulk change from the product list.
 *
 * @param  array<string, mixed>  $payload
 */
function fillSeries(User $user, Organization $organization, array $payload): TestResponse
{
    return test()
        ->actingAs($user)
        ->from(route('products.index', ['current_organization' => $organization->slug]))
        ->patch(route('products.bulk.update', ['current_organization' => $organization->slug]), $payload);
}

/**
 * Visit the product list with a query string.
 *
 * @param  array<string, string>  $query
 */
function seriesList(User $user, Organization $organization, array $query): TestResponse
{
    return test()
        ->actingAs($user)
        ->get(route('products.index', ['current_organization' => $organization->slug, ...$query]));
}

test('the missing scope agrees with the completeness score on every requirement', function (ProductRequirement $requirement) {
    [, $organization, $template] = distributorWithSeries(products: 0);
    $template->update(array_fill_keys(ProductRequirement::columns(), true));

    $connectionId = $organization->supplierConnections()->value('id');
    $base = ['supplier_connection_id' => $connectionId, 'product_category_id' => $template->product_category_id, 'product_template_id' => $template->id];

    $empty = Product::factory()->for($organization)->create([...$base, 'brand_id' => null, 'ean' => '  ', 'country_of_origin' => null]);

    $answered = Product::factory()->for($organization)->create($base);
    $attribute = $requirement->productAttribute();

    if ($attribute === 'brand_id') {
        $answered->update(['brand_id' => carriedBrand($answered->supplierConnection)->id]);
    } elseif ($attribute === 'country_of_origin') {
        $answered->update(['country_of_origin' => 'CN']);
    } elseif ($attribute !== null) {
        $answered->update([$attribute => '123456']);
    } else {
        ProductDocument::factory()->for($answered)->ofType($requirement->documentType())->create();
    }

    $missing = Product::query()->missingRequirement($requirement)->pluck('id')->all();

    $satisfied = fn (Product $product): bool => collect($product->fresh()->completeness()->items)
        ->firstWhere('requirement', $requirement->value)['satisfied'];

    expect(in_array($empty->id, $missing, true))->toBe(! $satisfied($empty))
        ->and(in_array($answered->id, $missing, true))->toBe(! $satisfied($answered))
        ->and(in_array($answered->id, $missing, true))->toBeFalse();
})->with(ProductRequirement::cases());

test('a requirement the template does not ask for is never missing', function () {
    [, , $template] = distributorWithSeries(products: 1);

    expect(Product::query()->missingRequirement(ProductRequirement::TestReport)->count())->toBe(0)
        ->and(Product::query()->missingRequirement(ProductRequirement::AgeGrading)->count())->toBe(1);
});

test('the product list filters to the products missing a requirement', function () {
    [$user, $organization] = distributorWithSeries(products: 2);

    $filled = $organization->products()->first();
    $filled->update(['age_grading' => '3+']);

    seriesList($user, $organization, ['missing' => ProductRequirement::AgeGrading->value])
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('filters.missing', ProductRequirement::AgeGrading->value)
            ->has('availableRequirements'));

    seriesList($user, $organization, ['missing' => 'nonsense'])
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('filters.missing', null));
});

test('a distributor fills the safety details of a whole series at once', function () {
    [$user, $organization] = distributorWithSeries();
    $ids = $organization->products()->pluck('id')->all();

    fillSeries($user, $organization, [
        'ids' => $ids,
        'age_grading' => '3+',
        'warning_text' => 'Not suitable for children under 3 years.',
        'customs_tariff_number' => '9503.00.95',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $products = Product::query()->whereKey($ids)->get();

    expect($products->pluck('age_grading')->unique()->all())->toBe(['3+'])
        ->and($products->pluck('customs_tariff_number')->unique()->all())->toBe(['95030095'])
        ->and($products->first()->events()->where('type', ProductEventType::Updated)->exists())->toBeTrue();
});

test('filling only the empty ones leaves a product its own answer', function () {
    [$user, $organization] = distributorWithSeries();
    $own = $organization->products()->first();
    $own->update(['age_grading' => '8+']);

    fillSeries($user, $organization, [
        'ids' => $organization->products()->pluck('id')->all(),
        'age_grading' => '3+',
        'only_empty' => true,
    ])->assertSessionHasNoErrors();

    expect($own->fresh()->age_grading)->toBe('8+')
        ->and($organization->products()->where('age_grading', '3+')->count())->toBe(2);

    fillSeries($user, $organization, [
        'ids' => [$own->id],
        'age_grading' => '3+',
        'only_empty' => false,
    ]);

    expect($own->fresh()->age_grading)->toBe('3+');
});

test('a bulk fill holds the same limits as a single product', function () {
    [$user, $organization] = distributorWithSeries(products: 1);

    fillSeries($user, $organization, [
        'ids' => $organization->products()->pluck('id')->all(),
        'customs_tariff_number' => 'abc',
        'age_grading' => str_repeat('x', 256),
    ])->assertSessionHasErrors(['customs_tariff_number', 'age_grading']);
});

test('a supplier may fill the safety details of its products in bulk', function () {
    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();
    $connection = newSupplierConnection($distributor, $supplier);

    $products = Product::factory()->for($distributor)->count(2)->create(['supplier_connection_id' => $connection->id]);

    fillSeries($supplierUser, $supplier, [
        'ids' => $products->pluck('id')->all(),
        'warning_text' => 'Contains small parts.',
    ])->assertSessionHasNoErrors();

    expect(Product::query()->whereKey($products->pluck('id'))->pluck('warning_text')->unique()->all())->toBe(['Contains small parts.']);
});

test('a distributor queue holds the drafts nobody else will fill in, after what is in review', function () {
    [$user, $organization] = distributorWithSeries(products: 2);
    [, $supplier] = newSupplierMember();

    $activeConnection = newSupplierConnection($organization, $supplier);
    $theirs = Product::factory()->for($organization)->create(['supplier_connection_id' => $activeConnection->id]);
    $inReview = Product::factory()->for($organization)->create([
        'supplier_connection_id' => $activeConnection->id,
        'review_status' => ProductReviewStatus::InReview,
        'submitted_at' => now(),
    ]);

    $draft = $organization->products()->where('supplier_connection_id', '!=', $activeConnection->id)->orderBy('id')->first();

    $this->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $draft->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('todo.total', 3)
            ->where('todo.position', 2)
            ->where('todo.previous.id', $inReview->id));

    $this->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $organization->slug, 'product' => $theirs->id]))
        ->assertInertia(fn (Assert $page) => $page->where('todo.position', null));
});

test('submitting a draft the distributor filled itself goes on to the next one', function () {
    [$user, $organization] = distributorWithSeries(products: 2);
    [$first, $second] = $organization->products()->orderBy('id')->get()->all();

    $this->actingAs($user)
        ->post(route('products.submit', ['current_organization' => $organization->slug, 'product' => $first->id]), ['next' => $second->id])
        ->assertRedirect(route('products.edit', ['current_organization' => $organization->slug, 'product' => $second->id]));
});

test('a distributor shows or hides every product image of a product at once', function () {
    [$user, $organization] = distributorWithSeries(products: 1);
    $product = $organization->products()->first();

    ProductDocument::factory()->for($product)->ofType(ProductDocumentType::ProductImage)->count(3)->create(['is_public' => false]);
    $report = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create();

    $this->actingAs($user)
        ->patch(route('products.images.visibility', ['current_organization' => $organization->slug, 'product' => $product->id]), ['is_public' => true])
        ->assertRedirect();

    expect($product->documents()->where('type', ProductDocumentType::ProductImage)->where('is_public', true)->count())->toBe(3)
        ->and($report->fresh()->is_public)->toBeFalse()
        ->and($product->events()->where('type', ProductEventType::DocumentPublished)->count())->toBe(3);

    $this->actingAs($user)
        ->patch(route('products.images.visibility', ['current_organization' => $organization->slug, 'product' => $product->id]), ['is_public' => false]);

    expect($product->documents()->where('is_public', true)->count())->toBe(0);
});

test('a supplier may not publish the images', function () {
    [, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();
    $product = Product::factory()->for($distributor)->create(['supplier_connection_id' => newSupplierConnection($distributor, $supplier)->id]);

    $this->actingAs($supplierUser)
        ->patch(route('products.images.visibility', ['current_organization' => $supplier->slug, 'product' => $product->id]), ['is_public' => true])
        ->assertForbidden();
});

test('the images already on show stay on show after the switch starts to count', function () {
    [, $organization] = distributorWithSeries(products: 1);
    $product = $organization->products()->first();

    $image = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::ProductImage)->create(['is_public' => false]);
    $report = ProductDocument::factory()->for($product)->ofType(ProductDocumentType::TestReport)->create();

    $migration = require database_path('migrations/2026_11_19_052358_publish_existing_product_images.php');
    $migration->up();

    expect($image->fresh()->is_public)->toBeTrue()
        ->and($report->fresh()->is_public)->toBeFalse()
        ->and(DB::table('product_documents')->count())->toBe(2);
});
