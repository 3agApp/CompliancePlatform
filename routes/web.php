<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDocumentController;
use App\Http\Controllers\Suppliers\DistributorConnectionController;
use App\Http\Controllers\Suppliers\SupplierConnectionClaimController;
use App\Http\Controllers\Suppliers\SupplierConnectionController;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\EnsureOrganizationType;
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

Route::prefix('{current_organization}')
    ->middleware(['auth', 'verified', EnsureOrganizationMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::scopeBindings()->group(function () {
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::get('products/{product}', [ProductController::class, 'edit'])->name('products.edit');
            Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

            Route::post('products/{product}/documents', [ProductDocumentController::class, 'store'])->name('products.documents.store');
            Route::get('products/{product}/documents/{document}', [ProductDocumentController::class, 'show'])->name('products.documents.show');
            Route::delete('products/{product}/documents/{document}', [ProductDocumentController::class, 'destroy'])->name('products.documents.destroy');

            Route::middleware(EnsureOrganizationType::class.':distributor')->group(function () {
                Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
                Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
                Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
                Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

                Route::get('categories', [ProductCategoryController::class, 'index'])->name('categories.index');
                Route::post('categories', [ProductCategoryController::class, 'store'])->name('categories.store');
                Route::patch('categories/{product_category}', [ProductCategoryController::class, 'update'])->name('categories.update');
                Route::delete('categories/{product_category}', [ProductCategoryController::class, 'destroy'])->name('categories.destroy');

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
