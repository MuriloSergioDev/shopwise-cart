<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Cart;

final readonly class CreateGuestCart
{
    public function handle(): Cart
    {
        return Cart::query()->create()->loadForDisplay();
    }
}
