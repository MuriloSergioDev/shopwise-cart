<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Cart;

/**
 * Stores (uppercased) or clears the coupon code. Not validated here: the pricing quote says whether it applies.
 */
final readonly class SetCartCoupon
{
    public function handle(Cart $cart, ?string $code): Cart
    {
        $cart->coupon_code = $code === null ? null : mb_strtoupper($code);

        $cart->touch();

        return $cart->loadForDisplay();
    }
}
