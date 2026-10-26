<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\CartItemData;
use App\Models\Cart;
use Illuminate\Foundation\Http\FormRequest;

final class CheckOutCartRequest extends FormRequest
{
    /**
     * Ids are capped at 26 characters (ULIDs), as cart.cart-checked-out requires.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'checkoutId' => ['required', 'string', 'max:26'],
            'orderId' => ['required', 'string', 'max:26'],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:'.Cart::MAX_LINES],
            'lines.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
        ];
    }

    /**
     * @return list<CartItemData>
     */
    public function lines(): array
    {
        /** @var list<array{sku: string, quantity: int|string}> $lines */
        $lines = $this->validated('lines');

        return array_map(fn (array $line): CartItemData => new CartItemData($line['sku'], (int) $line['quantity']), $lines);
    }
}
