<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CheckOutCart;
use App\Http\Requests\CheckOutCartRequest;
use Illuminate\Http\JsonResponse;

final class CheckOutCartController
{
    /**
     * 200 with the same body whether this call closed the cart or an earlier one with the same checkoutId did.
     */
    public function __invoke(CheckOutCartRequest $request, string $cartId, CheckOutCart $checkOutCart): JsonResponse
    {
        $cart = $checkOutCart->handle($cartId, $request->string('checkoutId')->toString(), $request->string('orderId')->toString(), $request->lines());

        return response()->json(['data' => [
            'cartId' => $cart->id,
            'checkoutId' => $cart->checkout_id,
            'orderId' => $cart->order_id,
            'closedAt' => $cart->checked_out_at?->toIso8601ZuluString(),
        ]]);
    }
}
