<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CartSnapshotResource;
use App\Models\Cart;
use Illuminate\Http\Request;
use Shopwise\Kit\Auth\JwtUser;

final class ShowCartSnapshotController
{
    /**
     * Checkout's copy of the caller's open customer cart. Guest, closed and other customers' carts are 404.
     */
    public function __invoke(Request $request, string $cartId): CartSnapshotResource
    {
        $user = $request->user();
        abort_unless($user instanceof JwtUser, 401);

        $cart = Cart::query()->open()->where('customer_id', $user->id)->findOrFail($cartId);

        return CartSnapshotResource::make($cart->load('lines.variant.product'));
    }
}
