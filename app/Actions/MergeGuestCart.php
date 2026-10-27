<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\MergeResult;
use App\Models\Cart;
use App\Models\CartLine;
use Illuminate\Support\Facades\DB;

/**
 * Moves a guest cart's lines into the customer's cart and deletes the guest cart. Same SKU: quantities are added,
 * capped at 99. New SKUs are appended in the guest cart's order while the customer cart has room (50 lines);
 * the rest are not added and come back as dropped SKUs. The guest coupon is kept only when the customer has none.
 * An unknown or already merged guest cart is a no-op, so the BFF can retry. A customer cart locked by a checkout is
 * 409 and the guest cart is kept.
 */
final readonly class MergeGuestCart
{
    public function __construct(private OpenCustomerCart $openCustomerCart) {}

    public function handle(string $customerId, string $guestCartId): MergeResult
    {
        $customerCart = $this->openCustomerCart->handle($customerId);

        return DB::transaction(function () use ($customerCart, $guestCartId): MergeResult {
            $customerCart = Cart::query()->open()->lockForUpdate()->findOrFail($customerCart->id);
            $guestCart = Cart::query()->whereNull('customer_id')->lockForUpdate()->find($guestCartId);
            if (! $guestCart instanceof Cart) {
                return new MergeResult($customerCart->loadForDisplay());
            }

            $customerCart->ensureUnlocked();

            $dropped = [];
            $lines = $customerCart->lines()->get()->keyBy('sku');
            foreach ($guestCart->lines()->get() as $guestLine) {
                $line = $lines->get($guestLine->sku);
                if ($line instanceof CartLine) {
                    $line->update(['quantity' => min(Cart::MAX_QUANTITY, $line->quantity + $guestLine->quantity)]);
                } elseif ($lines->count() < Cart::MAX_LINES) {
                    $lines->put($guestLine->sku, $customerCart->lines()->create(['sku' => $guestLine->sku, 'quantity' => $guestLine->quantity]));
                } else {
                    $dropped[] = $guestLine->sku;
                }
            }

            $guestCart->delete();
            $customerCart->recordChange(['coupon_code' => $customerCart->coupon_code ?? $guestCart->coupon_code]);

            return new MergeResult($customerCart->loadForDisplay(), $dropped);
        });
    }
}
