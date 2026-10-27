<?php

declare(strict_types=1);

use App\Models\Cart;
use Illuminate\Support\Facades\Date;

const LOCKING_CHECKOUT = '01JBCDEFGHJKMNPQRSTVWXYZ40';

it('takes the snapshot and locks the cart in one call', function (): void {
    Date::setTestNow('2026-10-26T12:00:00Z');
    sellable('TEE-MER-BLK-M');
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->withLines(['TEE-MER-BLK-M' => 2])->create(['coupon_code' => 'WELCOME10']);

    $this->postJson("/api/carts/{$cart->id}/snapshot", ['checkoutId' => LOCKING_CHECKOUT], bearer())
        ->assertOk()
        ->assertExactJson(['data' => [
            'cartId' => $cart->id,
            'customerId' => CUSTOMER_ID,
            'version' => 0,
            'couponCode' => 'WELCOME10',
            'lines' => [['sku' => 'TEE-MER-BLK-M', 'quantity' => 2, 'productName' => 'Product TEE-MER-BLK-M', 'variantName' => 'Variant TEE-MER-BLK-M', 'available' => true]],
            'takenAt' => '2026-10-26T12:00:00Z',
        ]]);

    expect($cart->refresh()->locked_by_checkout_id)->toBe(LOCKING_CHECKOUT);
    $this->getJson("/api/carts/{$cart->id}", bearer())->assertOk()->assertJsonPath('data.lockedByCheckoutId', LOCKING_CHECKOUT);
});

it('locks an empty cart too', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->postJson("/api/carts/{$cart->id}/snapshot", ['checkoutId' => LOCKING_CHECKOUT], bearer())->assertOk()->assertJsonPath('data.lines', []);

    expect($cart->refresh()->locked_by_checkout_id)->toBe(LOCKING_CHECKOUT);
});

it('is idempotent per checkout and refuses another checkout with cart_locked', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->postJson("/api/carts/{$cart->id}/snapshot", ['checkoutId' => LOCKING_CHECKOUT], bearer())->assertOk();
    $this->postJson("/api/carts/{$cart->id}/snapshot", ['checkoutId' => LOCKING_CHECKOUT], bearer())->assertOk();
    $this->postJson("/api/carts/{$cart->id}/snapshot", ['checkoutId' => '01JBCDEFGHJKMNPQRSTVWXYZ41'], bearer())
        ->assertConflict()
        ->assertExactJson(['message' => 'This cart is being checked out.', 'code' => 'cart_locked', 'checkoutId' => LOCKING_CHECKOUT]);

    expect($cart->refresh()->locked_by_checkout_id)->toBe(LOCKING_CHECKOUT)
        ->and($cart->version)->toBe(0);
});

it('guards the locking snapshot like the read-only one', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();
    $guest = Cart::factory()->create();
    $body = ['checkoutId' => LOCKING_CHECKOUT];

    $this->postJson("/api/carts/{$cart->id}/snapshot", $body)->assertUnauthorized();
    $this->postJson("/api/carts/{$cart->id}/snapshot", $body, bearer('01JBCDEFGHJKMNPQRSTVWXYZ77'))->assertNotFound();
    $this->postJson("/api/carts/{$guest->id}/snapshot", $body, bearer())->assertNotFound();
    $this->postJson("/api/carts/{$cart->id}/snapshot", ['checkoutId' => str_repeat('x', 27)], bearer())->assertUnprocessable()->assertJsonValidationErrors('checkoutId');
    $this->postJson("/api/carts/{$cart->id}/snapshot", [], bearer())->assertUnprocessable()->assertJsonValidationErrors('checkoutId');

    expect($cart->refresh()->locked_by_checkout_id)->toBeNull()
        ->and($guest->refresh()->locked_by_checkout_id)->toBeNull();
});

it('takes no lock with the deprecated GET', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->getJson("/api/carts/{$cart->id}/snapshot", bearer())->assertOk();

    expect($cart->refresh()->locked_by_checkout_id)->toBeNull();
});

it('refuses every edit while locked, but still serves reads', function (): void {
    sellable('TEE-MER-BLK-M');
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->withLines(['TEE-MER-BLK-M' => 2])->create(['coupon_code' => 'SAVE5', 'locked_by_checkout_id' => LOCKING_CHECKOUT]);
    $locked = ['message' => 'This cart is being checked out.', 'code' => 'cart_locked', 'checkoutId' => LOCKING_CHECKOUT];

    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 5], bearer())->assertConflict()->assertExactJson($locked);
    $this->deleteJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", [], bearer())->assertConflict()->assertExactJson($locked);
    $this->deleteJson("/api/carts/{$cart->id}/items/NOT-IN-CART", [], bearer())->assertConflict();
    $this->putJson("/api/carts/{$cart->id}/coupon", ['code' => 'OTHER'], bearer())->assertConflict()->assertExactJson($locked);
    $this->deleteJson("/api/carts/{$cart->id}/coupon", [], bearer())->assertConflict()->assertExactJson($locked);
    $this->getJson("/api/carts/{$cart->id}", bearer())->assertOk();
    $this->getJson('/api/me/cart', bearer())->assertOk()->assertJsonPath('data.id', $cart->id);

    $cart->refresh();
    expect($cart->lines()->sole()->quantity)->toBe(2)
        ->and($cart->coupon_code)->toBe('SAVE5')
        ->and($cart->version)->toBe(0);
});

it('unlocks for the checkout that holds the lock, and lets edits through again', function (): void {
    sellable('TEE-MER-BLK-M');
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create(['locked_by_checkout_id' => LOCKING_CHECKOUT]);

    $this->postJson("/internal/carts/{$cart->id}/unlock", ['checkoutId' => LOCKING_CHECKOUT])
        ->assertOk()
        ->assertExactJson(['data' => ['cartId' => $cart->id, 'lockedByCheckoutId' => null]]);
    // Idempotent.
    $this->postJson("/internal/carts/{$cart->id}/unlock", ['checkoutId' => LOCKING_CHECKOUT])
        ->assertOk()
        ->assertExactJson(['data' => ['cartId' => $cart->id, 'lockedByCheckoutId' => null]]);

    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 1], bearer())->assertOk();
});

it('leaves another checkout\'s lock alone', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create(['locked_by_checkout_id' => LOCKING_CHECKOUT]);

    $this->postJson("/internal/carts/{$cart->id}/unlock", ['checkoutId' => '01JBCDEFGHJKMNPQRSTVWXYZ41'])
        ->assertOk()
        ->assertExactJson(['data' => ['cartId' => $cart->id, 'lockedByCheckoutId' => LOCKING_CHECKOUT]]);

    expect($cart->refresh()->locked_by_checkout_id)->toBe(LOCKING_CHECKOUT);
});

it('404s unlocking an unknown or closed cart and validates the checkout id', function (): void {
    $closed = Cart::factory()->ownedBy(CUSTOMER_ID)->create(['checked_out_at' => now(), 'checkout_id' => LOCKING_CHECKOUT, 'order_id' => 'O1']);
    $cart = Cart::factory()->ownedBy('01JBCDEFGHJKMNPQRSTVWXYZ55')->create();

    $this->postJson('/internal/carts/unknown/unlock', ['checkoutId' => LOCKING_CHECKOUT])->assertNotFound();
    $this->postJson("/internal/carts/{$closed->id}/unlock", ['checkoutId' => LOCKING_CHECKOUT])->assertNotFound();
    $this->postJson("/internal/carts/{$cart->id}/unlock", ['checkoutId' => str_repeat('x', 27)])->assertUnprocessable()->assertJsonValidationErrors('checkoutId');
});
