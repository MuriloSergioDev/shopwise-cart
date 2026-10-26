<?php

declare(strict_types=1);

namespace App\Data;

final readonly class CartItemData
{
    public function __construct(
        public string $sku,
        public int $quantity,
    ) {}
}
