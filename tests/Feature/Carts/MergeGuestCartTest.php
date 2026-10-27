<?php

declare(strict_types=1);

use App\Models\Cart;

it('moves guest lines into the customer cart, adding quantities capped at 99, and deletes the guest cart', function (): void {
    foreach (['A', 'B', 'C', 'D'] as $sku) {
        sellable($sku);
    }

    $customerCart = Cart::factory()->ownedBy(CUSTOMER_ID)->withLines(['A' => 2, 'B' => 90])->create();
    $guestCart = Cart::factory()->withLines(['C' => 1, 'B' => 20, 'A' => 3, 'D' => 4])->create();

    $response = $this->postJson('/api/me/cart/merge', ['guestCartId' => $guestCart->id], bearer())
        ->assertOk()
        ->assertJsonPath('data.id', $customerCart->id)
        ->assertJsonPath('data.owner', 'customer')
        ->assertJsonPath('meta.droppedSkus', []);

    expect($response->json('data.lines.*.sku'))->toBe(['A', 'B', 'C', 'D'])
        ->and($response->json('data.lines.*.quantity'))->toBe([5, 99, 1, 4])
        ->and(Cart::query()->find($guestCart->id))->toBeNull()
        ->and($guestCart->lines()->count())->toBe(0);
});

it('creates the customer cart when merging before it exists', function (): void {
    sellable('A');
    $guestCart = Cart::factory()->withLines(['A' => 1])->create(['coupon_code' => 'WELCOME']);

    $this->postJson('/api/me/cart/merge', ['guestCartId' => $guestCart->id], bearer())
        ->assertOk()
        ->assertJsonPath('data.owner', 'customer')
        ->assertJsonPath('data.couponCode', 'WELCOME')
        ->assertJsonPath('data.lines.*.sku', ['A']);

    expect(Cart::query()->sole()->customer_id)->toBe(CUSTOMER_ID);
});

it('keeps the customer coupon over the guest one', function (): void {
    Cart::factory()->ownedBy(CUSTOMER_ID)->create(['coupon_code' => 'MINE']);
    $guestCart = Cart::factory()->create(['coupon_code' => 'GUEST']);

    $this->postJson('/api/me/cart/merge', ['guestCartId' => $guestCart->id], bearer())
        ->assertOk()
        ->assertJsonPath('data.couponCode', 'MINE');
});

it('is a no-op for an unknown or already merged guest cart', function (): void {
    sellable('A');
    $guestCart = Cart::factory()->withLines(['A' => 2])->create();

    $this->postJson('/api/me/cart/merge', ['guestCartId' => $guestCart->id], bearer())->assertOk()->assertJsonPath('data.lines.0.quantity', 2);
    $this->postJson('/api/me/cart/merge', ['guestCartId' => $guestCart->id], bearer())->assertOk()->assertJsonPath('data.lines.0.quantity', 2);
    $this->postJson('/api/me/cart/merge', ['guestCartId' => 'unknown'], bearer())->assertOk()->assertJsonPath('data.lines.0.quantity', 2);
});

it('never merges another customer cart', function (): void {
    $otherCart = Cart::factory()->ownedBy('01JBCDEFGHJKMNPQRSTVWXYZ77')->withLines(['A' => 1])->create();

    $this->postJson('/api/me/cart/merge', ['guestCartId' => $otherCart->id], bearer())->assertOk()->assertJsonPath('data.lines', []);

    expect($otherCart->lines()->count())->toBe(1);
});

it('stops adding new lines at 50', function (): void {
    $customerLines = [];
    foreach (range(1, 49) as $i) {
        $customerLines["SKU-{$i}"] = 1;
    }

    Cart::factory()->ownedBy(CUSTOMER_ID)->withLines($customerLines)->create();
    $guestCart = Cart::factory()->withLines(['SKU-1' => 1, 'NEW-1' => 1, 'NEW-2' => 1])->create();

    $guestCart->lines()->create(['sku' => 'NEW-3', 'quantity' => 1]);

    $response = $this->postJson('/api/me/cart/merge', ['guestCartId' => $guestCart->id], bearer())
        ->assertOk()
        ->assertJsonPath('meta.droppedSkus', ['NEW-2', 'NEW-3']);

    expect($response->json('data.lines'))->toHaveCount(50)
        ->and($response->json('data.lines.0.quantity'))->toBe(2)
        ->and($response->json('data.lines.49.sku'))->toBe('NEW-1');
});

it('needs a guest cart id', function (): void {
    $this->postJson('/api/me/cart/merge', [], bearer())->assertUnprocessable()->assertJsonValidationErrors('guestCartId');
});

it('refuses to merge into a cart locked by a checkout and keeps the guest cart', function (): void {
    sellable('A');
    $customerCart = Cart::factory()->ownedBy(CUSTOMER_ID)->create(['locked_by_checkout_id' => '01JBCDEFGHJKMNPQRSTVWXYZ40']);
    $guestCart = Cart::factory()->withLines(['A' => 1])->create();

    $this->postJson('/api/me/cart/merge', ['guestCartId' => $guestCart->id], bearer())
        ->assertConflict()
        ->assertExactJson(['message' => 'This cart is being checked out.', 'code' => 'cart_locked', 'checkoutId' => '01JBCDEFGHJKMNPQRSTVWXYZ40']);

    expect($guestCart->lines()->count())->toBe(1)
        ->and($customerCart->lines()->count())->toBe(0);
});
