<?php

declare(strict_types=1);

use App\Models\Cart;
use Illuminate\Support\Facades\Date;

it('gives checkout a snapshot of the caller\'s cart with lines in add order, coupon and version', function (): void {
    Date::setTestNow('2026-10-26T12:00:00Z');
    sellable('TEE-MER-BLK-M');
    sellable('MUG-BLK', active: false);
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->withLines(['TEE-MER-BLK-M' => 2, 'MUG-BLK' => 1])->create(['coupon_code' => 'WELCOME10']);

    $this->getJson("/api/carts/{$cart->id}/snapshot", bearer())
        ->assertOk()
        ->assertExactJson(['data' => [
            'cartId' => $cart->id,
            'customerId' => CUSTOMER_ID,
            'version' => 0,
            'couponCode' => 'WELCOME10',
            'lines' => [
                ['sku' => 'TEE-MER-BLK-M', 'quantity' => 2, 'productName' => 'Product TEE-MER-BLK-M', 'variantName' => 'Variant TEE-MER-BLK-M', 'available' => true],
                ['sku' => 'MUG-BLK', 'quantity' => 1, 'productName' => 'Product MUG-BLK', 'variantName' => 'Variant MUG-BLK', 'available' => false],
            ],
            'takenAt' => '2026-10-26T12:00:00Z',
        ]]);
});

it('snapshots an empty cart', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->getJson("/api/carts/{$cart->id}/snapshot", bearer())->assertOk()->assertJsonPath('data.lines', []);
});

it('increases the version on every change to the cart', function (): void {
    sellable('TEE-MER-BLK-M');
    sellable('MUG-BLK');
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();
    $version = fn (): int => $this->getJson("/api/carts/{$cart->id}/snapshot", bearer())->json('data.version');

    expect($version())->toBe(0);
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 1], bearer())->assertOk();
    expect($version())->toBe(1);
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 3], bearer())->assertOk();
    expect($version())->toBe(2);
    $this->putJson("/api/carts/{$cart->id}/coupon", ['code' => 'save5'], bearer())->assertOk();
    expect($version())->toBe(3);
    $this->deleteJson("/api/carts/{$cart->id}/coupon", [], bearer())->assertOk();
    expect($version())->toBe(4);
    $this->deleteJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", [], bearer())->assertOk();
    expect($version())->toBe(5);
    // Removing a SKU that is not in the cart changes nothing.
    $this->deleteJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", [], bearer())->assertOk();
    expect($version())->toBe(5);

    $guest = Cart::factory()->withLines(['MUG-BLK' => 1])->create();
    $this->postJson('/api/me/cart/merge', ['guestCartId' => $guest->id], bearer())->assertOk();
    expect($version())->toBe(6);
});

it('needs the owner\'s token and an open customer cart', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();
    $guest = Cart::factory()->create();
    $closed = Cart::factory()->ownedBy('01JBCDEFGHJKMNPQRSTVWXYZ55')->create(['checked_out_at' => now(), 'checkout_id' => 'C1', 'order_id' => 'O1']);

    $this->getJson("/api/carts/{$cart->id}/snapshot")->assertUnauthorized();
    $this->getJson("/api/carts/{$cart->id}/snapshot", ['Authorization' => 'Bearer nonsense'])->assertUnauthorized();
    $this->getJson("/api/carts/{$cart->id}/snapshot", bearer('01JBCDEFGHJKMNPQRSTVWXYZ77'))->assertNotFound();
    $this->getJson("/api/carts/{$guest->id}/snapshot", bearer())->assertNotFound();
    $this->getJson("/api/carts/{$closed->id}/snapshot", bearer('01JBCDEFGHJKMNPQRSTVWXYZ55'))->assertNotFound();
    $this->getJson('/api/carts/unknown/snapshot', bearer())->assertNotFound();
});
