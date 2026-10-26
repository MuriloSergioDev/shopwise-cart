<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\MergeGuestCart;
use App\Http\Requests\MergeGuestCartRequest;
use App\Http\Resources\CartResource;
use Shopwise\Kit\Auth\JwtUser;

final class MergeGuestCartController
{
    public function __invoke(MergeGuestCartRequest $request, MergeGuestCart $mergeGuestCart): CartResource
    {
        $user = $request->user();
        abort_unless($user instanceof JwtUser, 401);

        return CartResource::make($mergeGuestCart->handle($user->id, $request->guestCartId()));
    }
}
