<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\MergeGuestCart;
use App\Http\Requests\MergeGuestCartRequest;
use App\Http\Resources\CartResource;
use Illuminate\Http\JsonResponse;
use Shopwise\Kit\Auth\JwtUser;

final class MergeGuestCartController
{
    public function __invoke(MergeGuestCartRequest $request, MergeGuestCart $mergeGuestCart): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof JwtUser, 401);

        $result = $mergeGuestCart->handle($user->id, $request->guestCartId());

        return CartResource::make($result->cart)->additional(['meta' => ['droppedSkus' => $result->droppedSkus]])->response();
    }
}
