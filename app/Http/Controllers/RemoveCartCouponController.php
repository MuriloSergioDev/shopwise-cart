<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SetCartCoupon;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use Illuminate\Http\Request;

final class RemoveCartCouponController
{
    public function __invoke(Request $request, string $cartId, SetCartCoupon $setCartCoupon): CartResource
    {
        $cart = Cart::query()->accessibleBy($request->user())->findOrFail($cartId);

        return CartResource::make($setCartCoupon->handle($cart, null));
    }
}
