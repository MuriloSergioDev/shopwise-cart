<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Cart;

/**
 * The customer's open cart, created empty on first access or after a checkout closed the previous one. Open carts
 * are unique per `customer_id`, so concurrent first requests end up with the same cart.
 */
final readonly class OpenCustomerCart
{
    public function handle(string $customerId): Cart
    {
        return Cart::query()->open()->createOrFirst(['customer_id' => $customerId]);
    }
}
