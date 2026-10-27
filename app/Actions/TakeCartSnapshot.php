<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\CartLocked;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;

/**
 * Checkout's step 1 (ADR-0004): locks the customer's open cart for the checkout and returns it for the snapshot, in
 * one transaction. Idempotent per checkout: the same checkoutId keeps the lock and gets a fresh snapshot; another
 * checkout is refused. Locking is not a change to the cart's contents, so `version` stays.
 */
final readonly class TakeCartSnapshot
{
    /**
     * @throws CartLocked
     */
    public function handle(string $customerId, string $cartId, string $checkoutId): Cart
    {
        return DB::transaction(function () use ($customerId, $cartId, $checkoutId): Cart {
            $cart = Cart::query()->open()->where('customer_id', $customerId)->lockForUpdate()->findOrFail($cartId);

            if ($cart->locked_by_checkout_id !== $checkoutId) {
                $cart->ensureUnlocked();
                $cart->update(['locked_by_checkout_id' => $checkoutId]);
            }

            return $cart->load('lines.variant.product');
        });
    }
}
