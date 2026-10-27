<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\OpenCustomerCart;
use App\Http\Resources\CartResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shopwise\Kit\Auth\JwtUser;

final class ShowCustomerCartController
{
    public function __invoke(Request $request, OpenCustomerCart $openCustomerCart): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof JwtUser, 401);

        $cart = $openCustomerCart->handle($user->id);

        // 201 when this request created the cart (first access, or the last one was checked out).
        return CartResource::make($cart->loadForDisplay())->response()->setStatusCode($cart->wasRecentlyCreated ? 201 : 200);
    }
}
