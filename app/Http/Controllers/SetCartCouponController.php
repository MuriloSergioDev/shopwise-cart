<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SetCartCoupon;
use App\Http\Requests\SetCartCouponRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;

final class SetCartCouponController
{
    public function __invoke(SetCartCouponRequest $request, string $cartId, SetCartCoupon $setCartCoupon): CartResource
    {
        $cart = Cart::query()->accessibleBy($request->user())->findOrFail($cartId);

        return CartResource::make($setCartCoupon->handle($cart, $request->code()));
    }
}
