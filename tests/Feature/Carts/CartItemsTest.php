<?php

declare(strict_types=1);

use App\Models\Cart;

it('adds SKUs, sets quantities and keeps lines in first-add order', function (): void {
    sellable('A-1', 1000);
    sellable('B-2', 2000);
    $cart = Cart::factory()->create();
    $this->travel(1)->minutes();

    $this->putJson("/api/carts/{$cart->id}/items/B-2", ['quantity' => 1])->assertOk();
    $this->putJson("/api/carts/{$cart->id}/items/A-1", ['quantity' => 2])->assertOk();
    $response = $this->putJson("/api/carts/{$cart->id}/items/B-2", ['quantity' => 5])->assertOk();

    expect($response->json('data.lines.*.sku'))->toBe(['B-2', 'A-1'])
        ->and($response->json('data.lines.*.quantity'))->toBe([5, 2])
        ->and($response->json('data.updatedAt'))->toBe(now()->toIso8601ZuluString())
        ->and($cart->refresh()->updated_at->toIso8601ZuluString())->toBe(now()->toIso8601ZuluString());
});

it('rejects SKUs that are unknown or not for sale', function (string $sku): void {
    sellable('INACTIVE', active: false);
    sellable('DRAFT', published: false);
    $cart = Cart::factory()->create();

    $this->putJson("/api/carts/{$cart->id}/items/{$sku}", ['quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sku');

    expect($cart->lines()->count())->toBe(0);
})->with(['unknown' => 'NOPE', 'inactive variant' => 'INACTIVE', 'unpublished product' => 'DRAFT']);

it('validates the quantity', function (mixed $quantity): void {
    sellable('A-1');
    $cart = Cart::factory()->create();

    $this->putJson("/api/carts/{$cart->id}/items/A-1", ['quantity' => $quantity])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
})->with([0, 100, 'two', null]);

it('rejects a SKU longer than 64 characters', function (): void {
    $cart = Cart::factory()->create();

    $this->putJson("/api/carts/{$cart->id}/items/".str_repeat('A', 65), ['quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sku');
});

it('holds at most 50 lines but still updates existing ones', function (): void {
    $quantities = [];
    foreach (range(1, 50) as $i) {
        $quantities["SKU-{$i}"] = 1;
        sellable("SKU-{$i}");
    }

    sellable('SKU-51');
    $cart = Cart::factory()->withLines($quantities)->create();

    $this->putJson("/api/carts/{$cart->id}/items/SKU-51", ['quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
    $this->putJson("/api/carts/{$cart->id}/items/SKU-50", ['quantity' => 4])
        ->assertOk()
        ->assertJsonPath('data.lines.49.quantity', 4);
});

it('removes a line, and removing a missing SKU is a no-op', function (): void {
    sellable('A-1');
    sellable('B-2');
    $cart = Cart::factory()->withLines(['A-1' => 1, 'B-2' => 2])->create();

    $this->deleteJson("/api/carts/{$cart->id}/items/A-1")->assertOk()->assertJsonPath('data.lines.*.sku', ['B-2']);
    $this->deleteJson("/api/carts/{$cart->id}/items/A-1")->assertOk()->assertJsonPath('data.lines.*.sku', ['B-2']);
    $this->deleteJson('/api/carts/01JBCDEFGHJKMNPQRSTVWXYZ00/items/A-1')->assertNotFound();
});

it('shows lines that are no longer for sale as unavailable, with null display data where unknown', function (): void {
    sellable('GONE', unitAmount: null, active: false);
    $cart = Cart::factory()->withLines(['GONE' => 1, 'WIPED' => 1])->create();

    $this->getJson("/api/carts/{$cart->id}")->assertOk()->assertJsonPath('data.lines', [
        [
            'sku' => 'GONE', 'quantity' => 1, 'productSlug' => 'slug-gone', 'productName' => 'Product GONE',
            'variantName' => 'Variant GONE', 'available' => false, 'unitAmount' => null, 'currency' => null,
        ],
        [
            'sku' => 'WIPED', 'quantity' => 1, 'productSlug' => null, 'productName' => null,
            'variantName' => 'WIPED', 'available' => false, 'unitAmount' => null, 'currency' => null,
        ],
    ]);
});
