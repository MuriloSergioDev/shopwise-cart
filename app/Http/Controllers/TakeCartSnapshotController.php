<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\TakeCartSnapshot;
use App\Http\Requests\CheckoutIdRequest;
use App\Http\Resources\CartSnapshotResource;
use Illuminate\Http\JsonResponse;
use Shopwise\Kit\Auth\JwtUser;

final class TakeCartSnapshotController
{
    /**
     * The caller's open customer cart, now locked by the checkout. Guest, closed and other customers' carts are 404.
     */
    public function __invoke(CheckoutIdRequest $request, string $cartId, TakeCartSnapshot $takeCartSnapshot): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof JwtUser, 401);

        return CartSnapshotResource::make($takeCartSnapshot->handle($user->id, $cartId, $request->checkoutId()))->response()->setStatusCode(200);
    }
}
