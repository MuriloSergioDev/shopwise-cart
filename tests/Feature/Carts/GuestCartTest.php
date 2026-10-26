<?php

declare(strict_types=1);

use App\Models\Cart;

it('creates an empty guest cart', function (): void {
    $response = $this->postJson('/api/carts')->assertCreated();

    $cart = Cart::query()->sole();
    $response->assertExactJson(['data' => [
        'id' => $cart->id,
        'owner' => 'guest',
        'couponCode' => null,
        'lines' => [],
        'updatedAt' => $cart->updated_at->toIso8601ZuluString(),
    ]]);
    expect($cart->customer_id)->toBeNull();
});

it('shows a guest cart by id alone, with or without a token', function (): void {
    sellable('TEE-MER-BLK-M', 2900);
    $cart = Cart::factory()->withLines(['TEE-MER-BLK-M' => 2])->create();

    $this->getJson("/api/carts/{$cart->id}")
        ->assertOk()
        ->assertJsonPath('data.owner', 'guest')
        ->assertJsonPath('data.lines', [[
            'sku' => 'TEE-MER-BLK-M',
            'quantity' => 2,
            'productSlug' => 'slug-tee-mer-blk-m',
            'productName' => 'Product TEE-MER-BLK-M',
            'variantName' => 'Variant TEE-MER-BLK-M',
            'available' => true,
            'unitAmount' => 2900,
            'currency' => 'USD',
        ]]);

    $this->getJson("/api/carts/{$cart->id}", bearer())->assertOk()->assertJsonPath('data.id', $cart->id);
});

it('returns 404 for an unknown cart', function (): void {
    $this->getJson('/api/carts/01JBCDEFGHJKMNPQRSTVWXYZ00')->assertNotFound();
});

it('rejects an invalid token even on a guest cart', function (): void {
    $cart = Cart::factory()->create();

    $this->getJson("/api/carts/{$cart->id}", ['Authorization' => 'Bearer not-a-jwt'])->assertUnauthorized();
});
