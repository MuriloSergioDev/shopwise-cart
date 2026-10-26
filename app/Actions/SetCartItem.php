<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\CartItemData;
use App\Models\Cart;
use App\Models\CatalogVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a SKU or sets its quantity. Only SKUs that are for sale (active variant of a published product) can be set.
 */
final readonly class SetCartItem
{
    public function handle(Cart $cart, CartItemData $item): Cart
    {
        return DB::transaction(function () use ($cart, $item): Cart {
            // Serialises writers of the same cart, so the line limit holds.
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);

            $variant = CatalogVariant::query()->with('product')->where('sku', $item->sku)->first();
            if (! $variant instanceof CatalogVariant || ! $variant->isForSale()) {
                throw ValidationException::withMessages(['sku' => "SKU {$item->sku} is not for sale."]);
            }

            $line = $cart->lines()->where('sku', $item->sku)->first();
            if ($line === null) {
                if ($cart->lines()->count() >= Cart::MAX_LINES) {
                    throw ValidationException::withMessages(['items' => 'A cart holds at most '.Cart::MAX_LINES.' lines.']);
                }

                $cart->lines()->create(['sku' => $item->sku, 'quantity' => $item->quantity]);
            } else {
                $line->update(['quantity' => $item->quantity]);
            }

            $cart->touch();

            return $cart->loadForDisplay();
        });
    }
}
