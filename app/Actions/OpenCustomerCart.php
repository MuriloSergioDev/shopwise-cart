<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Cart;

/**
 * The customer's cart, created empty on first access. `customer_id` is unique, so concurrent first requests
 * end up with the same cart.
 */
final readonly class OpenCustomerCart
{
    public function handle(string $customerId): Cart
    {
        return Cart::query()->createOrFirst(['customer_id' => $customerId]);
    }
}
