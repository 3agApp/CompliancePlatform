<?php

use App\Enums\Locale;
use App\Enums\OrganizationRole;
use App\Http\Middleware\SetLocale;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Notifications\Suppliers\SupplierConnectionInvitation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('a member who has not chosen a language reads the app in the organization default', function () {
    [$user, $organization] = newOrganizationMember(organizationAttributes: ['locale' => Locale::German]);

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_organization' => $organization->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'de')
            ->where('translations.Dashboard', 'Übersicht')
            ->where('pipeline.0.label', 'Entwurf'),
        );
});

test('a member who chose a language reads the app in it whatever the organization speaks', function () {
    [$user, $organization] = newOrganizationMember(organizationAttributes: ['locale' => Locale::German]);
    $user->update(['locale' => Locale::English]);

    $this
        ->actingAs($user)
        ->get(route('dashboard', ['current_organization' => $organization->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'en')
            ->where('translations', [])
            ->where('pipeline.0.label', 'Draft'),
        );
});

test('a guest reads the login page in the language their browser asks for', function () {
    $this
        ->withHeader('Accept-Language', 'de-CH,de;q=0.9,en;q=0.5')
        ->get(route('login'))
        ->assertSee('<html lang="de"', escape: false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'de'));
});

test('a language a guest picked on this browser wins over what the browser asks for', function () {
    $this
        ->withUnencryptedCookie(SetLocale::COOKIE, 'en')
        ->withHeader('Accept-Language', 'de')
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('a member chooses a language for themselves and the browser remembers it', function () {
    [$user] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->patch(route('language.update'), ['locale' => 'de'])
        ->assertRedirect()
        ->assertCookie(SetLocale::COOKIE, 'de', encrypted: false);

    expect($user->fresh()->locale)->toBe(Locale::German);
});

test('a member goes back to following the organization and the browser follows it too', function () {
    [$user] = newOrganizationMember(organizationAttributes: ['locale' => Locale::German]);
    $user->update(['locale' => Locale::English]);

    $this
        ->actingAs($user)
        ->patch(route('language.update'), ['locale' => null])
        ->assertCookie(SetLocale::COOKIE, 'de', encrypted: false);

    expect($user->fresh()->locale)->toBeNull();
});

test('a guest switches language without an account', function () {
    $this
        ->patch(route('language.update'), ['locale' => 'de'])
        ->assertRedirect()
        ->assertCookie(SetLocale::COOKIE, 'de', encrypted: false);
});

test('only the languages the app speaks can be chosen', function () {
    [$user] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->patch(route('language.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');

    expect($user->fresh()->locale)->toBeNull();
});

test('an owner sets the organization default language', function () {
    [$user, $organization] = newOrganizationMember();

    $this
        ->actingAs($user)
        ->patch(route('organizations.update', $organization), [
            'name' => $organization->name,
            'locale' => 'de',
        ])
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->locale)->toBe(Locale::German);
});

test('a member cannot change the organization default language', function () {
    [, $organization] = newOrganizationMember();
    $member = User::factory()->withoutOrganization()->create();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $this
        ->actingAs($member)
        ->patch(route('organizations.update', $organization), [
            'name' => $organization->name,
            'locale' => 'de',
        ])
        ->assertForbidden();

    expect($organization->fresh()->locale)->toBe(Locale::English);
});

test('a new organization speaks the language its founder is using and starts with categories in it', function () {
    $user = User::factory()->withoutOrganization()->create(['locale' => Locale::German]);

    $this
        ->actingAs($user)
        ->post(route('organizations.store'), ['name' => 'Alpenwerk AG', 'type' => 'distributor']);

    $organization = Organization::where('name', 'Alpenwerk AG')->sole();

    expect($organization->locale)->toBe(Locale::German)
        ->and($organization->productCategories()->pluck('name')->all())
        ->toEqualCanonicalizing(['Spielzeug', 'Magnetisches Spielzeug', 'Filter']);
});

test('a supplier invitation goes out in the distributor language', function () {
    Notification::fake();

    [$user, $distributor] = newOrganizationMember(organizationAttributes: ['locale' => Locale::German]);
    $user->update(['locale' => Locale::English]);

    $this
        ->actingAs($user)
        ->post(route('suppliers.store', ['current_organization' => $distributor->slug]), [
            'company_name' => 'Acme Supplies',
            'contact_email' => 'compliance@acme.test',
        ]);

    Notification::assertSentOnDemand(
        SupplierConnectionInvitation::class,
        fn (SupplierConnectionInvitation $notification) => $notification->locale === 'de',
    );
});

test('validation messages are written in the reader language', function () {
    [$user, $distributor] = newOrganizationMember(organizationAttributes: ['locale' => Locale::German]);

    $this
        ->actingAs($user)
        ->post(route('suppliers.store', ['current_organization' => $distributor->slug]), [
            'contact_email' => 'compliance@acme.test',
        ])
        ->assertSessionHasErrors(['company_name' => 'Das Feld Firmenname ist erforderlich.']);
});

test('product history names its fields in the reader language', function () {
    [$user, $distributor] = newOrganizationMember(organizationAttributes: ['locale' => Locale::German]);
    $product = Product::factory()->for($distributor)->create(['supplier_connection_id' => newSupplierConnection($distributor)->id]);

    $this
        ->actingAs($user)
        ->get(route('products.edit', ['current_organization' => $distributor->slug, 'product' => $product->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.review_status_label', 'Entwurf')
            ->where('availableRequirements.0.label', 'Prüfbericht'),
        );
});

/**
 * A string added to the app without a German line falls back to English,
 * quietly, in the middle of a German page. This is what notices.
 */
test('every string the application shows has a German translation', function () {
    $german = json_decode(File::get(lang_path('de.json')), true, flags: JSON_THROW_ON_ERROR);

    $unquote = fn (string $literal): string => stripcslashes(substr($literal, 1, -1));
    $keys = [];

    /**
     * The public pages read by buyers keep their own dictionary, and the
     * generated and vendored folders hold no strings of ours.
     */
    $ownDictionary = ['public-i18n.ts', 'products/public.tsx', 'pages/check.tsx', 'product-unit-check.tsx', 'public-header.tsx', 'captcha-field.tsx'];
    $generated = ['/actions/', '/routes/', '/wayfinder/', '/components/ui/'];

    foreach (File::allFiles(resource_path('js')) as $file) {
        $path = str_replace('\\', '/', $file->getPathname());

        if (collect([...$ownDictionary, ...$generated])->contains(fn (string $skip) => str_contains($path, $skip))) {
            continue;
        }

        preg_match_all('/\b(?:t|tc|tn|tcn|tk)\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/', $file->getContents(), $matches);
        array_push($keys, ...array_map($unquote, $matches[1]));
    }

    foreach ([...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))] as $file) {
        preg_match_all('/(?:__|trans_choice)\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/', $file->getContents(), $matches);
        array_push($keys, ...array_map($unquote, $matches[1]));
    }

    $missing = array_values(array_diff(array_unique($keys), array_keys($german)));

    expect($keys)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});
