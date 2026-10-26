<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
final class CartFactory extends Factory
{
    public function definition(): array
    {
        return ['customer_id' => null, 'coupon_code' => null];
    }

    public function ownedBy(string $customerId): self
    {
        return $this->state(['customer_id' => $customerId]);
    }

    /**
     * @param  array<string, int>  $quantities  SKU => quantity, in add order
     */
    public function withLines(array $quantities): self
    {
        return $this->afterCreating(function (Cart $cart) use ($quantities): void {
            foreach ($quantities as $sku => $quantity) {
                $cart->lines()->create(['sku' => $sku, 'quantity' => $quantity]);
            }
        });
    }
}
