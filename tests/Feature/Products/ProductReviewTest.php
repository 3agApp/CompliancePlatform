<?php

use App\Enums\Locale;
use App\Enums\OrganizationRole;
use App\Enums\ProductDocumentType;
use App\Enums\ProductEventType;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductSealStatus;
use App\Enums\SupplierConnectionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Notifications\Products\ProductChangesRequested;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Set up a distributor, the supplier they work with, and one product
 * assigned to that trade.
 *
 * @return array{0: User, 1: Organization, 2: User, 3: Organization, 4: Product}
 */
function tradeWithProduct(ProductReviewStatus $status = ProductReviewStatus::Draft): array
{
    [$distributorUser, $distributor] = newOrganizationMember();
    [$supplierUser, $supplier] = newSupplierMember();

    $connection = newSupplierConnection($distributor, $supplier);

    $product = Product::factory()
        ->for($distributor)
        ->reviewed($status)
        ->create([
            'name' => 'Magnetic Building Set',
            'supplier_connection_id' => $connection->id,
        ]);

    return [$distributorUser, $distributor, $supplierUser, $supplier, $product];
}

test('a new product starts as a draft and says so in its history', function () {
    [$user, $organization] = newOrganizationMember();
    $connection = newSupplierConnection($organization);
    $template = familyTemplate($organization);

    $this
        ->actingAs($user)
        ->post(route('products.store', ['current_organization' => $organization->slug]), [
            'name' => 'Magnetic Building Set',
            'supplier_connection_id' => $connection->id,
            'product_category_id' => $template->product_category_id,
            'product_template_id' => $template->id,
        ])
        ->assertSessionHasNoErrors();

    $product = Product::query()->sole();

    expect($product->review_status)->toBe(ProductReviewStatus::Draft)
        ->and($product->submitted_at)->toBeNull();

    $event = $product->events()->sole();

    expect($event->type)->toBe(ProductEventType::Created)
        ->and($event->actor_name)->toBe($user->name)
        ->and($event->actor_organization_name)->toBe($organization->name);
});

test('a supplier submits a product and the distributor approves it', function () {
    [$distributorUser, $distributor, $supplierUser, $supplier, $product] = tradeWithProduct();

    $this
        ->actingAs($supplierUser)
        ->post(route('products.submit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertRedirect();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::InReview)
        ->and($product->fresh()->submitted_at)->not->toBeNull();

    $this
        ->actingAs($distributorUser)
        ->post(route('products.approve', ['current_organization' => $distributor->slug, 'product' => $product->id]))
        ->assertRedirect();

    $product->refresh();

    expect($product->review_status)->toBe(ProductReviewStatus::Approved)
        ->and($product->reviewed_at)->not->toBeNull()
        ->and($product->events()->get()->pluck('type.value')->all())
        ->toBe([ProductEventType::Approved->value, ProductEventType::Submitted->value]);
});

test('the distributor sends a product back with a note the supplier can read', function () {
    [$distributorUser, $distributor, $supplierUser, $supplier, $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $this
        ->actingAs($distributorUser)
        ->post(route('products.request-changes', ['current_organization' => $distributor->slug, 'product' => $product->id]), [
            'note' => 'The test report covers the 2021 article number, not this one.',
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::ChangesRequested);

    $this
        ->actingAs($supplierUser)
        ->get(route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.review_status', 'changes_requested')
            ->where('reviewNote', 'The test report covers the 2021 article number, not this one.'),
        );
});

test('sending a product back emails the people at the supplier who can make the changes', function () {
    Notification::fake();

    [$distributorUser, $distributor, $supplierOwner, $supplier, $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $supplierAdmin = User::factory()->withoutOrganization()->create();
    $supplier->members()->attach($supplierAdmin, ['role' => OrganizationRole::Admin->value]);

    $supplierMember = User::factory()->withoutOrganization()->create();
    $supplier->members()->attach($supplierMember, ['role' => OrganizationRole::Member->value]);

    $this
        ->actingAs($distributorUser)
        ->post(route('products.request-changes', ['current_organization' => $distributor->slug, 'product' => $product->id]), [
            'note' => "Please send:\n\n1. An EN 71-3 test report.\n2. A signed declaration of conformity.",
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo([$supplierOwner, $supplierAdmin], ProductChangesRequested::class);
    Notification::assertNotSentTo([$supplierMember, $distributorUser], ProductChangesRequested::class);

    Notification::assertSentTo($supplierOwner, ProductChangesRequested::class, function (ProductChangesRequested $notification) use ($supplierOwner, $supplier, $product, $distributorUser): bool {
        $mail = $notification->toMail($supplierOwner);

        return str_contains($mail->subject, 'Magnetic Building Set')
            && array_slice($mail->introLines, 1) === ['Please send:', '1. An EN 71-3 test report.', '2. A signed declaration of conformity.']
            && str_contains($mail->introLines[0], $distributorUser->name)
            && $mail->actionUrl === route('products.edit', ['current_organization' => $supplier->slug, 'product' => $product->id]);
    });
});

test('a supplier who has not claimed the connection, or whose connection is revoked, is not emailed', function (SupplierConnectionStatus $status) {
    Notification::fake();

    [$distributorUser, $distributor] = newOrganizationMember();
    [, $supplier] = newSupplierMember();

    $product = Product::factory()->for($distributor)->reviewed(ProductReviewStatus::InReview)->create([
        'supplier_connection_id' => newSupplierConnection($distributor, $supplier, ['status' => $status])->id,
    ]);

    $this
        ->actingAs($distributorUser)
        ->post(route('products.request-changes', ['current_organization' => $distributor->slug, 'product' => $product->id]), [
            'note' => 'The test report is for another article.',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
})->with([SupplierConnectionStatus::Revoked]);

test('a connection nobody has claimed yet sends no email', function () {
    Notification::fake();

    [$distributorUser, $distributor] = newOrganizationMember();

    $product = Product::factory()->for($distributor)->reviewed(ProductReviewStatus::InReview)->create([
        'supplier_connection_id' => newSupplierConnection($distributor)->id,
    ]);

    $this
        ->actingAs($distributorUser)
        ->post(route('products.request-changes', ['current_organization' => $distributor->slug, 'product' => $product->id]), [
            'note' => 'The test report is for another article.',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

test('the email reaches each person in their own language', function () {
    Notification::fake();

    [$distributorUser, $distributor, $supplierOwner, , $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $supplierOwner->update(['locale' => Locale::German]);

    $this
        ->actingAs($distributorUser)
        ->post(route('products.request-changes', ['current_organization' => $distributor->slug, 'product' => $product->id]), [
            'note' => 'Bitte EN 71-3 nachreichen.',
        ]);

    Notification::assertSentTo(
        $supplierOwner,
        ProductChangesRequested::class,
        fn (ProductChangesRequested $notification, array $channels, User $notifiable, ?string $locale): bool => $locale === 'de',
    );
});

test('sending a product back without saying why is refused', function () {
    [$distributorUser, $distributor, , , $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $this
        ->actingAs($distributorUser)
        ->post(route('products.request-changes', ['current_organization' => $distributor->slug, 'product' => $product->id]), ['note' => '  '])
        ->assertSessionHasErrors('note');

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::InReview);
});

test('the distributor takes back an approval given by mistake', function () {
    [$distributorUser, $distributor, , , $product] = tradeWithProduct(ProductReviewStatus::Approved);

    $this
        ->actingAs($distributorUser)
        ->post(route('products.reopen', ['current_organization' => $distributor->slug, 'product' => $product->id]), [
            'note' => 'Approved by mistake: the test report has not been checked yet.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $product->refresh();

    expect($product->review_status)->toBe(ProductReviewStatus::InReview)
        ->and($product->reviewed_at)->toBeNull()
        ->and($product->seal()->status)->not->toBe(ProductSealStatus::Verified);

    $event = $product->events()->latest('id')->first();

    expect($event->type)->toBe(ProductEventType::ApprovalRevoked)
        ->and($event->note)->toBe('Approved by mistake: the test report has not been checked yet.')
        ->and($event->actor_name)->toBe($distributorUser->name);
});

test('taking back an approval without saying why is refused', function () {
    [$distributorUser, $distributor, , , $product] = tradeWithProduct(ProductReviewStatus::Approved);

    $this
        ->actingAs($distributorUser)
        ->post(route('products.reopen', ['current_organization' => $distributor->slug, 'product' => $product->id]), ['note' => '  '])
        ->assertSessionHasErrors('note');

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::Approved);
});

test('only an approved product can have its approval taken back', function () {
    [$distributorUser, $distributor, , , $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $this
        ->actingAs($distributorUser)
        ->post(route('products.reopen', ['current_organization' => $distributor->slug, 'product' => $product->id]), ['note' => 'Wrong one.'])
        ->assertStatus(409);

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::InReview);
});

test('a supplier cannot take back an approval', function () {
    [, , $supplierUser, $supplier, $product] = tradeWithProduct(ProductReviewStatus::Approved);

    $this
        ->actingAs($supplierUser)
        ->post(route('products.reopen', ['current_organization' => $supplier->slug, 'product' => $product->id]), ['note' => 'Please look again.'])
        ->assertForbidden();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::Approved);
});

test('a supplier cannot sign off their own homework', function () {
    [, , $supplierUser, $supplier, $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $this
        ->actingAs($supplierUser)
        ->post(route('products.approve', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertForbidden();

    $this
        ->actingAs($supplierUser)
        ->post(route('products.request-changes', ['current_organization' => $supplier->slug, 'product' => $product->id]), ['note' => 'Looks fine to me.'])
        ->assertForbidden();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::InReview);
});

test('a member of the distributor can see the review but not rule on it', function () {
    [, $distributor, , , $product] = tradeWithProduct(ProductReviewStatus::InReview);

    [$member] = newOrganizationMember();
    $distributor->members()->attach($member, ['role' => OrganizationRole::Member->value]);
    $member->switchOrganization($distributor);

    $this
        ->actingAs($member)
        ->get(route('products.edit', ['current_organization' => $distributor->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('permissions.canReviewProduct', false));

    $this
        ->actingAs($member)
        ->post(route('products.approve', ['current_organization' => $distributor->slug, 'product' => $product->id]))
        ->assertForbidden();
});

test('a product cannot be submitted twice or approved before it is submitted', function () {
    [$distributorUser, $distributor, $supplierUser, $supplier, $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $this
        ->actingAs($supplierUser)
        ->post(route('products.submit', ['current_organization' => $supplier->slug, 'product' => $product->id]))
        ->assertStatus(409);

    /** Not fillable: the status only ever moves through a review action. */
    $product->forceFill(['review_status' => ProductReviewStatus::Draft])->save();

    $this
        ->actingAs($distributorUser)
        ->post(route('products.approve', ['current_organization' => $distributor->slug, 'product' => $product->id]))
        ->assertStatus(409);
});

test('a supplier editing a submitted product puts it back in draft', function () {
    [, , $supplierUser, $supplier, $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $this
        ->actingAs($supplierUser)
        ->patch(route('products.update', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'name' => $product->name,
            'supplier_connection_id' => $product->supplier_connection_id,
            'product_category_id' => $product->product_category_id,
            'product_template_id' => $product->product_template_id,
            'warning_text' => 'Not suitable for children under 3 years.',
        ])
        ->assertSessionHasNoErrors();

    $product->refresh();

    expect($product->review_status)->toBe(ProductReviewStatus::Draft)
        ->and($product->submitted_at)->toBeNull()
        ->and($product->events()->get()->pluck('type.value')->all())
        ->toBe([ProductEventType::ReturnedToDraft->value, ProductEventType::Updated->value]);

    $update = $product->events()->where('type', ProductEventType::Updated)->sole();

    expect($update->changes)->toHaveKey('warning_text')
        ->and($update->changes['warning_text']['to'])->toBe('Not suitable for children under 3 years.');
});

test('a distributor editing a submitted product leaves the review alone', function () {
    [$distributorUser, $distributor, , , $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $this
        ->actingAs($distributorUser)
        ->patch(route('products.update', ['current_organization' => $distributor->slug, 'product' => $product->id]), [
            'name' => 'Magnetic Building Set (2026)',
            'supplier_connection_id' => $product->supplier_connection_id,
            'product_category_id' => $product->product_category_id,
            'product_template_id' => $product->product_template_id,
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::InReview)
        ->and($product->events()->get()->pluck('type.value')->all())->toBe([ProductEventType::Updated->value]);
});

test('a form submitted without changing anything is not an edit', function () {
    [, , $supplierUser, $supplier, $product] = tradeWithProduct(ProductReviewStatus::InReview);

    $this
        ->actingAs($supplierUser)
        ->patch(route('products.update', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'name' => $product->name,
            'supplier_connection_id' => $product->supplier_connection_id,
            'product_category_id' => $product->product_category_id,
            'product_template_id' => $product->product_template_id,
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->review_status)->toBe(ProductReviewStatus::InReview)
        ->and($product->events()->count())->toBe(0);
});

test('a supplier filing a paper against an approved product puts it back in draft', function () {
    Storage::fake('local');

    [, , $supplierUser, $supplier, $product] = tradeWithProduct(ProductReviewStatus::Approved);

    $this
        ->actingAs($supplierUser)
        ->post(route('products.documents.store', ['current_organization' => $supplier->slug, 'product' => $product->id]), [
            'documents' => [
                ['type' => ProductDocumentType::TestReport->value, 'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf')],
            ],
        ])
        ->assertSessionHasNoErrors();

    $product->refresh();

    expect($product->review_status)->toBe(ProductReviewStatus::Draft)
        ->and($product->events()->get()->pluck('type.value')->all())
        ->toBe([ProductEventType::ReturnedToDraft->value, ProductEventType::DocumentUploaded->value]);

    $upload = $product->events()->where('type', ProductEventType::DocumentUploaded)->sole();

    expect($upload->changes['document']['to'])->toBe('report.pdf');
});

test('the catalogue can be narrowed to what is waiting on the reviewer', function () {
    [$distributorUser, $distributor, , , $waiting] = tradeWithProduct(ProductReviewStatus::InReview);

    Product::factory()->for($distributor)->create([
        'name' => 'Something Else',
        'supplier_connection_id' => $waiting->supplier_connection_id,
    ]);

    $this
        ->actingAs($distributorUser)
        ->get(route('products.index', ['current_organization' => $distributor->slug, 'status' => 'in_review']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $waiting->id)
            ->where('products.data.0.review_status', 'in_review')
            ->where('filters.status', 'in_review'),
        );

    /** A status nothing is called is not a filter, so the whole list shows. */
    $this
        ->actingAs($distributorUser)
        ->get(route('products.index', ['current_organization' => $distributor->slug, 'status' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 2)->where('filters.status', null));
});

test('the history is deferred and reads back who did what', function () {
    [$distributorUser, $distributor, $supplierUser, $supplier, $product] = tradeWithProduct();

    $this
        ->actingAs($supplierUser)
        ->post(route('products.submit', ['current_organization' => $supplier->slug, 'product' => $product->id]));

    $this
        ->actingAs($distributorUser)
        ->post(route('products.request-changes', ['current_organization' => $distributor->slug, 'product' => $product->id]), [
            'note' => 'We still need the declaration of conformity.',
        ]);

    $this
        ->actingAs($distributorUser)
        ->get(route('products.edit', ['current_organization' => $distributor->slug, 'product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('history'));

    /**
     * The history arrives on the follow-up request Inertia makes for it,
     * which is a JSON response rather than a page render.
     */
    $this
        ->actingAs($distributorUser)
        ->get(
            route('products.edit', ['current_organization' => $distributor->slug, 'product' => $product->id]),
            [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'products/edit',
                'X-Inertia-Partial-Data' => 'history',
                /** Without it Inertia answers a stale asset version, not the page. */
                'X-Inertia-Version' => Inertia::getVersion(),
            ],
        )
        ->assertOk()
        ->assertJsonCount(2, 'props.history')
        ->assertJsonPath('props.history.0.type', 'changes_requested')
        ->assertJsonPath('props.history.0.actor', $distributorUser->name)
        ->assertJsonPath('props.history.0.actor_organization', $distributor->name)
        ->assertJsonPath('props.history.0.note', 'We still need the declaration of conformity.')
        ->assertJsonPath('props.history.1.type', 'submitted')
        ->assertJsonPath('props.history.1.actor', $supplierUser->name)
        ->assertJsonPath('props.history.1.actor_organization', $supplier->name);
});

test('a product keeps what a closed account did to it', function () {
    [, , $supplierUser, $supplier, $product] = tradeWithProduct();

    $product->recordEvent(ProductEventType::Submitted, $supplierUser, $supplier);

    $supplierUser->delete();

    $event = $product->events()->sole();

    expect($event->user_id)->toBeNull()
        ->and($event->actorName())->toBe($supplierUser->name)
        ->and($event->actorOrganizationName())->toBe($supplier->name);
});
