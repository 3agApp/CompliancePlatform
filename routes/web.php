<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Suppliers\DistributorConnectionController;
use App\Http\Controllers\Suppliers\SupplierConnectionClaimController;
use App\Http\Controllers\Suppliers\SupplierConnectionController;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\EnsureOrganizationType;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('onboarding', OnboardingController::class)
    ->middleware(['auth', 'verified'])
    ->name('onboarding');

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

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [OrganizationInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [OrganizationInvitationController::class, 'decline'])->name('invitations.decline');

    Route::post('connections/{connection:code}', [SupplierConnectionClaimController::class, 'store'])->name('connections.store');
    Route::delete('connections/{connection:code}', [SupplierConnectionClaimController::class, 'destroy'])->name('connections.destroy');
});

require __DIR__.'/settings.php';
