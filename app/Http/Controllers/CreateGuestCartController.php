<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateGuestCart;
use App\Http\Resources\CartResource;
use Illuminate\Http\JsonResponse;

final class CreateGuestCartController
{
    public function __invoke(CreateGuestCart $createGuestCart): JsonResponse
    {
        return CartResource::make($createGuestCart->handle())->response()->setStatusCode(201);
    }
}
