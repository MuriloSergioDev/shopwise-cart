<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RemoveCartItem;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use Illuminate\Http\Request;

final class RemoveCartItemController
{
    public function __invoke(Request $request, string $cartId, string $sku, RemoveCartItem $removeCartItem): CartResource
    {
        $cart = Cart::query()->accessibleBy($request->user())->findOrFail($cartId);

        return CartResource::make($removeCartItem->handle($cart, $sku));
    }
}
