<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\CartItemData;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;
use Shopwise\Contracts\Events\Cart\CartCheckedOutV1;
use Shopwise\Kit\Messaging\Outbox;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Closes a customer cart after checkout placed the order (ADR-0004 step 7) and records cart.cart-checked-out with
 * the snapshot's lines. Idempotent per checkout: closing it again with the same checkoutId changes nothing and
 * records no second event; a different checkoutId is a conflict. Guest carts are unknown (404): guests cannot
 * check out.
 */
final readonly class CheckOutCart
{
    /**
     * @param  list<CartItemData>  $lines
     */
    public function handle(string $cartId, string $checkoutId, string $orderId, array $lines): Cart
    {
        return DB::transaction(function () use ($cartId, $checkoutId, $orderId, $lines): Cart {
            // Serialises with edits, which lock the open cart too.
            $cart = Cart::query()->whereNotNull('customer_id')->lockForUpdate()->findOrFail($cartId);

            if ($cart->isCheckedOut()) {
                if ($cart->checkout_id !== $checkoutId) {
                    throw new ConflictHttpException("Cart {$cartId} was closed by another checkout.");
                }

                return $cart;
            }

            $checkedOutAt = now();
            $cart->recordChange(['checked_out_at' => $checkedOutAt, 'checkout_id' => $checkoutId, 'order_id' => $orderId]);

            $event = new CartCheckedOutV1(
                cartId: $cart->id,
                customerId: (string) $cart->customer_id,
                checkoutId: $checkoutId,
                orderId: $orderId,
                lines: array_map(fn (CartItemData $line): array => ['sku' => $line->sku, 'quantity' => $line->quantity], $lines),
                checkedOutAt: $checkedOutAt->toIso8601ZuluString(),
            );
            Outbox::record(CartCheckedOutV1::TYPE, $cart->id, $event->toArray(), CartCheckedOutV1::SCHEMA);

            return $cart;
        });
    }
}
