<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Cart;
use App\Models\CartLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape defined in shopwise-contracts/openapi/cart.yaml (components.schemas.Cart). Expects `Cart::loadForDisplay()`.
 *
 * @mixin Cart
 */
final class CartResource extends JsonResource
{
    /**
     * @return array{id: string, owner: string, couponCode: string|null, lines: list<array{sku: string, quantity: int, productSlug: string|null, productName: string|null, variantName: string, available: bool, unitAmount: int|null, currency: string|null}>, updatedAt: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner' => $this->isGuest() ? 'guest' : 'customer',
            'couponCode' => $this->coupon_code,
            'lines' => array_values($this->lines->map(fn (CartLine $line): array => [
                'sku' => $line->sku,
                'quantity' => $line->quantity,
                'productSlug' => $line->variant?->product?->slug,
                'productName' => $line->variant?->product?->name,
                // A line is only ever added for a known variant; the SKU is a fallback for a wiped read model.
                'variantName' => $line->variant->name ?? $line->sku,
                'available' => $line->variant?->isForSale() ?? false,
                'unitAmount' => $line->price?->unit_amount,
                'currency' => $line->price?->currency,
            ])->all()),
            'updatedAt' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
