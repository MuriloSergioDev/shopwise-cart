<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Cart;
use Illuminate\Support\Facades\DB;

/**
 * Removes a checkout's lock when the checkout failed or was refused after the snapshot (ADR-0004). Idempotent: a cart
 * that is not locked, or is locked by another checkout, is left as it is. Unknown or closed carts are 404.
 */
final readonly class UnlockCart
{
    public function handle(string $cartId, string $checkoutId): Cart
    {
        return DB::transaction(function () use ($cartId, $checkoutId): Cart {
            $cart = Cart::query()->open()->lockForUpdate()->findOrFail($cartId);

            if ($cart->locked_by_checkout_id === $checkoutId) {
                $cart->update(['locked_by_checkout_id' => null]);
            }

            return $cart;
        });
    }
}
