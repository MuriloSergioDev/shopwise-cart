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

        // 200 even when this request created the cart: the contract treats creation as an implementation detail.
        return CartResource::make($openCustomerCart->handle($user->id)->loadForDisplay())->response()->setStatusCode(200);
    }
}
