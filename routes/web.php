<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\CheckUnitController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDocumentController;
use App\Http\Controllers\ProductDocumentVisibilityController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\ProductSealController;
use App\Http\Controllers\ProductTemplateController;
use App\Http\Controllers\PublicProductController;
use App\Http\Controllers\SerialLabelController;
use App\Http\Controllers\Suppliers\DistributorConnectionController;
use App\Http\Controllers\Suppliers\SupplierConnectionClaimController;
use App\Http\Controllers\Suppliers\SupplierConnectionController;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\EnsureOrganizationType;
use App\Http\Middleware\SetPublicLocale;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::delete('impersonation', [ImpersonationController::class, 'destroy'])
    ->middleware('auth')
    ->name('impersonation.destroy');

Route::get('onboarding', OnboardingController::class)
    ->middleware(['auth', 'verified'])
    ->name('onboarding');

// Every real dashboard is under an organization. This is the bare path
// Fortify redirects to from config('fortify.home').
Route::get('dashboard', DashboardRedirectController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard.redirect');

/**
 * The product's public face, off a label or a QR code: no account, no
 * organization, and addressed by the uuid so nobody can walk the catalogue
 * by counting. Declared before the organization prefix so the two-segment
 * shape is read as this and not as somebody's slug.
 */
Route::middleware(['throttle:public-product', SetPublicLocale::class])->group(function () {
    Route::get('p/{product:uuid}', [PublicProductController::class, 'show'])->name('products.public');

    Route::get('p/{product:uuid}/images/{document}', [PublicProductController::class, 'image'])
        ->scopeBindings()
        ->name('products.public.image');

    Route::get('p/{product:uuid}/documents/{document}', [PublicProductController::class, 'document'])
        ->scopeBindings()
        ->name('products.public.document');
});

/**
 * One packet, off its serialised label. Throttled harder than the plain
 * page, because a serial is the one thing on it worth guessing at.
 */
Route::middleware(['throttle:unit-check', SetPublicLocale::class])->group(function () {
    Route::get('p/{product:uuid}/u/{serial}', [PublicProductController::class, 'unit'])
        ->where('serial', '[0-9A-Za-z\-]{12,20}')
        ->name('products.public.unit');

    Route::post('p/{product:uuid}/check', [PublicProductController::class, 'check'])->name('products.public.check');

    Route::get('captcha', CaptchaController::class)->name('captcha');

    Route::get('check', [CheckUnitController::class, 'show'])->name('check');
    Route::post('check', [CheckUnitController::class, 'store'])->name('check.store');
});

Route::prefix('{current_organization}')
    ->middleware(['auth', 'verified', EnsureOrganizationMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::scopeBindings()->group(function () {
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            // Declared before products/{product} so "create" is a page, not a product key.
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::get('products/{product}', [ProductController::class, 'edit'])->name('products.edit');
            Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

            /**
             * Handing the product between the two sides. A move rather than
             * an edit, so each one posts to its own address and carries
             * nothing but the reviewer's note.
             */
            Route::post('products/{product}/submit', [ProductReviewController::class, 'submit'])->name('products.submit');
            Route::post('products/{product}/approve', [ProductReviewController::class, 'approve'])->name('products.approve');
            Route::post('products/{product}/request-changes', [ProductReviewController::class, 'requestChanges'])->name('products.request-changes');
            Route::post('products/{product}/reopen', [ProductReviewController::class, 'reopen'])->name('products.reopen');

            /**
             * Runs of serialised labels, one serial per packet.
             */
            Route::post('products/{product}/label-batches', [SerialLabelController::class, 'store'])->name('products.label-batches.store');
            Route::get('products/{product}/label-batches/{label_batch}', [SerialLabelController::class, 'show'])->name('products.label-batches.show');
            Route::get('products/{product}/label-batches/{label_batch}/pdf', [SerialLabelController::class, 'pdf'])->name('products.label-batches.pdf');
            Route::delete('products/{product}/label-batches/{label_batch}', [SerialLabelController::class, 'destroy'])->name('products.label-batches.destroy');

            /**
             * The public seal, set by hand. A distributor-only move, and one
             * the product's history keeps a line about either way.
             */
            Route::patch('products/{product}/seal', [ProductSealController::class, 'update'])->name('products.seal.update');

            Route::post('products/{product}/documents', [ProductDocumentController::class, 'store'])->name('products.documents.store');

            /**
             * Declared before documents/{document} so "suggestions" is a
             * question about the batch, not a document key.
             */
            Route::post('products/{product}/documents/suggestions', [ProductDocumentController::class, 'suggest'])
                ->middleware('throttle:ai-suggestions')
                ->name('products.documents.suggest');

            Route::get('products/{product}/documents/{document}', [ProductDocumentController::class, 'show'])->name('products.documents.show');

            /**
             * The same file as show, shown rather than handed over, so a
             * reader can check a certificate without collecting it.
             */
            Route::get('products/{product}/documents/{document}/preview', [ProductDocumentController::class, 'preview'])
                ->name('products.documents.preview');
            Route::delete('products/{product}/documents/{document}', [ProductDocumentController::class, 'destroy'])->name('products.documents.destroy');

            /**
             * Releasing a document to the public page, or taking it back.
             */
            Route::patch('products/{product}/documents/{document}/visibility', ProductDocumentVisibilityController::class)->name('products.documents.visibility');

            /**
             * Brands are named under a supplier connection, so both sides
             * of a trade reach them: the supplier because the maker is
             * theirs to name, the distributor because it is their catalog.
             * They sit outside the distributor-only group for that reason.
             */
            Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
            Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
            Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
            Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

            Route::middleware(EnsureOrganizationType::class.':distributor')->group(function () {
                Route::get('categories', [ProductCategoryController::class, 'index'])->name('categories.index');
                Route::post('categories', [ProductCategoryController::class, 'store'])->name('categories.store');
                Route::patch('categories/{product_category}', [ProductCategoryController::class, 'update'])->name('categories.update');
                Route::delete('categories/{product_category}', [ProductCategoryController::class, 'destroy'])->name('categories.destroy');

                Route::post('categories/{product_category}/templates', [ProductTemplateController::class, 'store'])->name('categories.templates.store');
                Route::patch('categories/{product_category}/templates/{template}', [ProductTemplateController::class, 'update'])->name('categories.templates.update');
                Route::delete('categories/{product_category}/templates/{template}', [ProductTemplateController::class, 'destroy'])->name('categories.templates.destroy');

                Route::get('suppliers', [SupplierConnectionController::class, 'index'])->name('suppliers.index');
                Route::post('suppliers', [SupplierConnectionController::class, 'store'])->name('suppliers.store');
                Route::post('suppliers/{supplier_connection}/resend', [SupplierConnectionController::class, 'resend'])->name('suppliers.resend');
                Route::patch('suppliers/{supplier_connection}/revoke', [SupplierConnectionController::class, 'revoke'])->name('suppliers.revoke');
                Route::patch('suppliers/{supplier_connection}/restore', [SupplierConnectionController::class, 'restore'])->name('suppliers.restore');
            });

            Route::middleware(EnsureOrganizationType::class.':supplier')->group(function () {
                Route::get('distributors', [DistributorConnectionController::class, 'index'])->name('distributors.index');
            });
        });
    });

Route::get('invitations', [OrganizationInvitationController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('invitations.index');

Route::get('connections/{connection:code}', [SupplierConnectionClaimController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('connections.show');

// Accepting an invitation joins an account to an organization that was
// invited by email, so the address has to be proven before it is used.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('invitations/{invitation}/accept', [OrganizationInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [OrganizationInvitationController::class, 'decline'])->name('invitations.decline');

    Route::post('connections/{connection:code}', [SupplierConnectionClaimController::class, 'store'])->name('connections.store');
    Route::delete('connections/{connection:code}', [SupplierConnectionClaimController::class, 'destroy'])->name('connections.destroy');
});

require __DIR__.'/settings.php';
