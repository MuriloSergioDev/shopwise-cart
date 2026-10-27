<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UnlockCart;
use App\Http\Requests\CheckoutIdRequest;
use Illuminate\Http\JsonResponse;

final class UnlockCartController
{
    /**
     * 200 with the cart's lock after the call (null once unlocked).
     */
    public function __invoke(CheckoutIdRequest $request, string $cartId, UnlockCart $unlockCart): JsonResponse
    {
        $cart = $unlockCart->handle($cartId, $request->checkoutId());

        return response()->json(['data' => ['cartId' => $cart->id, 'lockedByCheckoutId' => $cart->locked_by_checkout_id]]);
    }
}
