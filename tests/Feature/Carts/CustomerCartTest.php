<?php

declare(strict_types=1);

use App\Models\Cart;

it('creates the customer cart on first access and returns the same one afterwards', function (): void {
    $first = $this->getJson('/api/me/cart', bearer())->assertOk()
        ->assertJsonPath('data.owner', 'customer')
        ->assertJsonPath('data.lines', [])
        ->assertJsonPath('data.couponCode', null);

    $this->getJson('/api/me/cart', bearer())->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

    expect(Cart::query()->sole()->customer_id)->toBe(CUSTOMER_ID);
});

it('needs a token for the customer cart', function (): void {
    $this->getJson('/api/me/cart')->assertUnauthorized();
    $this->postJson('/api/me/cart/merge', ['guestCartId' => 'x'])->assertUnauthorized();
});

it('serves a customer cart by id to its owner only, and 404 to anyone else', function (): void {
    sellable('TEE-MER-BLK-M');
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->getJson("/api/carts/{$cart->id}", bearer())->assertOk()->assertJsonPath('data.owner', 'customer');
    $this->getJson("/api/carts/{$cart->id}")->assertNotFound();
    $this->getJson("/api/carts/{$cart->id}", bearer('01JBCDEFGHJKMNPQRSTVWXYZ77'))->assertNotFound();

    $other = bearer('01JBCDEFGHJKMNPQRSTVWXYZ77');
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 1], $other)->assertNotFound();
    $this->deleteJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", [], $other)->assertNotFound();
    $this->putJson("/api/carts/{$cart->id}/coupon", ['code' => 'SAVE10'], $other)->assertNotFound();
    $this->deleteJson("/api/carts/{$cart->id}/coupon", [], $other)->assertNotFound();
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 1])->assertNotFound();

    expect($cart->lines()->count())->toBe(0)
        ->and($cart->refresh()->coupon_code)->toBeNull();
});

it('lets the owner change their cart by id', function (): void {
    sellable('TEE-MER-BLK-M');
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 3], bearer())
        ->assertOk()
        ->assertJsonPath('data.lines.0.quantity', 3);
});
