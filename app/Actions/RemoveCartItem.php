<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Cart;
use Illuminate\Support\Facades\DB;

/**
 * Removes a SKU. Removing one that is not in the cart is a no-op (the cart is not touched).
 */
final readonly class RemoveCartItem
{
    public function handle(Cart $cart, string $sku): Cart
    {
        return DB::transaction(function () use ($cart, $sku): Cart {
            // Serialises with checkout locking and closing the cart: a locked or closed cart is not edited.
            $cart = Cart::query()->open()->lockForUpdate()->findOrFail($cart->id);
            $cart->ensureUnlocked();
            if ($cart->lines()->where('sku', $sku)->delete() > 0) {
                $cart->recordChange();
            }

            return $cart->loadForDisplay();
        });
    }
}
