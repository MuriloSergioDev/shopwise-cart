<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A change to a cart that a checkout has locked (ADR-0004). Rendered as the contract's 409 CartLockedError.
 */
final class CartLocked extends RuntimeException
{
    public function __construct(public readonly string $checkoutId)
    {
        parent::__construct('This cart is being checked out.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'cart_locked', 'checkoutId' => $this->checkoutId], 409);
    }
}
