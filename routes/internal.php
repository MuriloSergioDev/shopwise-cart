<?php

declare(strict_types=1);

use App\Http\Controllers\CheckOutCartController;
use App\Http\Controllers\UnlockCartController;
use Illuminate\Support\Facades\Route;

// Service-to-service routes (ADR-0004): reachable only inside the cluster, no JWT. The BFFs never proxy /internal
// and no ingress routes it. Contract: shopwise-contracts/openapi/cart.yaml.
Route::post('/carts/{cartId}/checked-out', CheckOutCartController::class)->name('carts.checked-out');
Route::post('/carts/{cartId}/unlock', UnlockCartController::class)->name('carts.unlock');
