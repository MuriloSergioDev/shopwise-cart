<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SetCartItem;
use App\Http\Requests\SetCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;

final class SetCartItemController
{
    public function __invoke(SetCartItemRequest $request, string $cartId, SetCartItem $setCartItem): CartResource
    {
        $cart = Cart::query()->accessibleBy($request->user())->findOrFail($cartId);

        return CartResource::make($setCartItem->handle($cart, $request->toData()));
    }
}
