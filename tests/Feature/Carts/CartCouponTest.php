<?php

declare(strict_types=1);

use App\Models\Cart;

it('stores the coupon code uppercased and removes it', function (): void {
    $cart = Cart::factory()->create();

    $this->putJson("/api/carts/{$cart->id}/coupon", ['code' => 'save-10_x'])->assertOk()->assertJsonPath('data.couponCode', 'SAVE-10_X');
    expect($cart->refresh()->coupon_code)->toBe('SAVE-10_X');

    $this->deleteJson("/api/carts/{$cart->id}/coupon")->assertOk()->assertJsonPath('data.couponCode', null);
    expect($cart->refresh()->coupon_code)->toBeNull();
});

it('validates the coupon code', function (mixed $code): void {
    $cart = Cart::factory()->create();

    $this->putJson("/api/carts/{$cart->id}/coupon", ['code' => $code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
})->with(['missing' => null, 'too long' => str_repeat('A', 33), 'bad characters' => 'SAVE 10%']);

it('returns 404 for the coupon of an unknown cart', function (): void {
    $this->putJson('/api/carts/01JBCDEFGHJKMNPQRSTVWXYZ00/coupon', ['code' => 'SAVE10'])->assertNotFound();
    $this->deleteJson('/api/carts/01JBCDEFGHJKMNPQRSTVWXYZ00/coupon')->assertNotFound();
});
