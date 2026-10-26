<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CheckOutCart;
use App\Http\Requests\CheckOutCartRequest;
use Illuminate\Http\Response;

final class CheckOutCartController
{
    /**
     * 200 with no body, whether this call closed the cart or an earlier one with the same checkoutId did.
     */
    public function __invoke(CheckOutCartRequest $request, string $cartId, CheckOutCart $checkOutCart): Response
    {
        $checkOutCart->handle($cartId, $request->string('checkoutId')->toString(), $request->string('orderId')->toString(), $request->lines());

        return response()->noContent(200);
    }
}
