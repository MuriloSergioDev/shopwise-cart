<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Cart;
use App\Models\CartLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape defined in shopwise-contracts/openapi/cart.yaml (components.schemas.CartSnapshot). Expects a customer cart
 * with `lines.variant.product` loaded.
 *
 * @mixin Cart
 */
final class CartSnapshotResource extends JsonResource
{
    /**
     * @return array{cartId: string, customerId: string|null, version: int, couponCode: string|null, lines: list<array{sku: string, quantity: int, productName: string|null, variantName: string, available: bool}>, takenAt: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'cartId' => $this->id,
            'customerId' => $this->customer_id,
            'version' => $this->version,
            'couponCode' => $this->coupon_code,
            'lines' => array_values($this->lines->map(fn (CartLine $line): array => [
                'sku' => $line->sku,
                'quantity' => $line->quantity,
                'productName' => $line->variant?->product?->name,
                'variantName' => $line->variant->name ?? $line->sku,
                'available' => $line->variant?->isForSale() ?? false,
            ])->all()),
            'takenAt' => now()->toIso8601ZuluString(),
        ];
    }
}
