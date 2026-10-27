<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Cart;

/**
 * The customer cart after a merge, and the guest SKUs that did not fit (the cart reached its line limit).
 */
final readonly class MergeResult
{
    /**
     * @param  list<string>  $droppedSkus
     */
    public function __construct(
        public Cart $cart,
        public array $droppedSkus = [],
    ) {}
}
