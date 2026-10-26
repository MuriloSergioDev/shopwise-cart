<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CartResource;
use App\Models\Cart;
use Illuminate\Http\Request;

final class ShowCartController
{
    public function __invoke(Request $request, string $cartId): CartResource
    {
        return CartResource::make(Cart::query()->accessibleBy($request->user())->findOrFail($cartId)->loadForDisplay());
    }
}
