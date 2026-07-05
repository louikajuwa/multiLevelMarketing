<?php

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PartnerLevelController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TreeController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

/*
 | Guest routes
 */
Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');

    Route::get('forgot-password', [PasswordController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [PasswordController::class, 'sendResetLink'])->name('password.email');

    Route::get('reset-password/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordController::class, 'resetPassword'])->name('password.update');
});

/*
 | Authenticated routes
 */
Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [PasswordController::class, 'changePassword'])->name('profile.password');

    /*
     | Customers (admin sees all; partners scoped to their own)
     */
    Route::get('customers', [CustomerController::class, 'index'])
        ->middleware('permission:customers.view')->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])
        ->middleware('permission:customers.create')->name('customers.store');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('permission:customers.update')->name('customers.update');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('permission:customers.delete')->name('customers.destroy');

    /*
     | Orders (admin all; partner own network; customer own)
     */
    Route::get('orders', [OrderController::class, 'index'])
        ->middleware('permission:orders.view')->name('orders.index');
    Route::post('orders', [OrderController::class, 'store'])
        ->middleware('permission:orders.create')->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])
        ->middleware('permission:orders.view')->name('orders.show');
    Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware('permission:orders.update_status')->name('orders.status');

    /*
     | Commissions (admin all; partner own earnings)
     */
    Route::get('commissions', [CommissionController::class, 'index'])
        ->middleware('permission:commissions.view')->name('commissions.index');
    Route::post('commissions/adjust', [CommissionController::class, 'adjust'])
        ->middleware('permission:commissions.adjust')->name('commissions.adjust');

    /*
     | MLM tree (lazy)
     */
    Route::get('tree', [TreeController::class, 'index'])
        ->middleware('permission:tree.view')->name('tree.index');
    Route::get('tree/nodes', [TreeController::class, 'nodes'])
        ->middleware('permission:tree.view')->name('tree.nodes');

    /*
     | Reports (Excel / CSV / PDF)
     */
    Route::get('reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])
        ->middleware('permission:reports.view')->name('reports.export');

    /*
     | Admin area
     */
    Route::prefix('admin')->name('admin.')->group(function (): void {
        // Partner levels
        Route::get('partner-levels', [PartnerLevelController::class, 'index'])
            ->middleware('permission:partner_levels.view')->name('partner-levels.index');
        Route::post('partner-levels', [PartnerLevelController::class, 'store'])
            ->middleware('permission:partner_levels.create')->name('partner-levels.store');
        Route::put('partner-levels/{partner_level}', [PartnerLevelController::class, 'update'])
            ->middleware('permission:partner_levels.update')->name('partner-levels.update');
        Route::delete('partner-levels/{partner_level}', [PartnerLevelController::class, 'destroy'])
            ->middleware('permission:partner_levels.delete')->name('partner-levels.destroy');

        // Partners
        Route::get('partners', [PartnerController::class, 'index'])
            ->middleware('permission:partners.view')->name('partners.index');
        Route::post('partners', [PartnerController::class, 'store'])
            ->middleware('permission:partners.create')->name('partners.store');
        Route::put('partners/{partner}', [PartnerController::class, 'update'])
            ->middleware('permission:partners.update')->name('partners.update');
        Route::delete('partners/{partner}', [PartnerController::class, 'destroy'])
            ->middleware('permission:partners.delete')->name('partners.destroy');

        // Catalog: brands
        Route::get('brands', [BrandController::class, 'index'])->middleware('permission:brands.view')->name('brands.index');
        Route::post('brands', [BrandController::class, 'store'])->middleware('permission:brands.create')->name('brands.store');
        Route::put('brands/{brand}', [BrandController::class, 'update'])->middleware('permission:brands.update')->name('brands.update');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->middleware('permission:brands.delete')->name('brands.destroy');

        // Catalog: categories
        Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:categories.view')->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:categories.create')->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update')->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete')->name('categories.destroy');

        // Catalog: products
        Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
    });
});
