<?php

declare(strict_types=1);

use App\Http\Controllers\CreateGuestCartController;
use App\Http\Controllers\MergeGuestCartController;
use App\Http\Controllers\RemoveCartCouponController;
use App\Http\Controllers\RemoveCartItemController;
use App\Http\Controllers\SetCartCouponController;
use App\Http\Controllers\SetCartItemController;
use App\Http\Controllers\ShowCartController;
use App\Http\Controllers\ShowCartSnapshotController;
use App\Http\Controllers\ShowCustomerCartController;
use App\Http\Controllers\TakeCartSnapshotController;
use Illuminate\Support\Facades\Route;

// Called only by the BFFs. Contract: shopwise-contracts/openapi/cart.yaml.
Route::post('/carts', CreateGuestCartController::class)->name('carts.store');

// Checkout's copy of the caller's customer cart (ADR-0004 step 1). Guests cannot check out, so a token is required.
// POST also locks the cart for the checkout; GET is deprecated and takes no lock.
Route::post('/carts/{cartId}/snapshot', TakeCartSnapshotController::class)->middleware('auth.jwt')->name('carts.snapshot.store');
Route::get('/carts/{cartId}/snapshot', ShowCartSnapshotController::class)->middleware('auth.jwt')->name('carts.snapshot');

Route::middleware('auth.jwt')->prefix('me/cart')->name('me.cart.')->group(function (): void {
    Route::get('/', ShowCustomerCartController::class)->name('show');
    Route::post('/merge', MergeGuestCartController::class)->name('merge');
});

// A guest cart is reachable by id alone; a customer cart only with its owner's token (otherwise 404).
Route::middleware('auth.jwt.optional')->prefix('carts/{cartId}')->name('carts.')->group(function (): void {
    Route::get('/', ShowCartController::class)->name('show');
    Route::put('/items/{sku}', SetCartItemController::class)->name('items.update');
    Route::delete('/items/{sku}', RemoveCartItemController::class)->name('items.destroy');
    Route::put('/coupon', SetCartCouponController::class)->name('coupon.update');
    Route::delete('/coupon', RemoveCartCouponController::class)->name('coupon.destroy');
});
