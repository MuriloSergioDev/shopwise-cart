<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\CartItemData;
use App\Models\Cart;
use Illuminate\Foundation\Http\FormRequest;

final class SetCartItemRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
        ];
    }

    /**
     * The SKU comes from the path; validate it with the body.
     *
     * @return array<array-key, mixed>
     */
    public function validationData(): array
    {
        return [...$this->all(), 'sku' => $this->route('sku')];
    }

    public function toData(): CartItemData
    {
        return new CartItemData(
            sku: (string) $this->route('sku'),
            quantity: $this->integer('quantity'),
        );
    }
}
