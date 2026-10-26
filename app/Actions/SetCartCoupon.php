<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Cart;
use Illuminate\Support\Facades\DB;

/**
 * Stores (uppercased) or clears the coupon code. Not validated here: the pricing quote says whether it applies.
 */
final readonly class SetCartCoupon
{
    public function handle(Cart $cart, ?string $code): Cart
    {
        return DB::transaction(function () use ($cart, $code): Cart {
            // Serialises with checkout closing the cart: a closed cart is not edited.
            $cart = Cart::query()->open()->lockForUpdate()->findOrFail($cart->id);
            $cart->recordChange(['coupon_code' => $code === null ? null : mb_strtoupper($code)]);

            return $cart->loadForDisplay();
        });
    }
}
