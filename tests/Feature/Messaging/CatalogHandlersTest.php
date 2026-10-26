<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use Shopwise\Kit\Messaging\DispatchResult;
use Shopwise\Kit\Messaging\MessageDispatcher;

it('makes a published product\'s variants sellable, exactly once', function (): void {
    $envelope = contractExample('catalog.product-published.v1');
    $dispatcher = app(MessageDispatcher::class);

    expect($dispatcher->dispatch($envelope, 'catalog.product-published'))->toBe(DispatchResult::Handled)
        ->and($dispatcher->dispatch($envelope, 'catalog.product-published'))->toBe(DispatchResult::Duplicate);

    $product = CatalogProduct::query()->sole();
    expect($product->only(['id', 'slug', 'name']))->toBe(['id' => '01JBCDEFGHJKMNPQRSTVWXYZ21', 'slug' => 'merino-crew-tee', 'name' => 'Merino Crew Tee'])
        ->and(CatalogVariant::query()->with('product')->orderBy('sku')->get()->map(fn (CatalogVariant $variant): array => [$variant->sku, $variant->name, $variant->isForSale()])->all())
        ->toBe([['TEE-MER-BLK-L', 'Black / L', true], ['TEE-MER-BLK-M', 'Black / M', true]]);

    $cart = Cart::factory()->create();
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 1])
        ->assertOk()
        ->assertJsonPath('data.lines.0.productSlug', 'merino-crew-tee')
        ->assertJsonPath('data.lines.0.variantName', 'Black / M');
});

it('stores draft variants that only become sellable once the product is published', function (): void {
    $dispatcher = app(MessageDispatcher::class);
    $cart = Cart::factory()->create();

    $dispatcher->dispatch(contractExample('catalog.variant-changed.v1'), 'catalog.variant-changed');
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('sku');

    $dispatcher->dispatch(contractExample('catalog.product-published.v1', ['publishedAt' => '2026-10-26T12:05:00Z']), 'catalog.product-published');
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 1])->assertOk();
});

it('deactivates and renames a variant, so cart lines show it unavailable', function (): void {
    $dispatcher = app(MessageDispatcher::class);
    $dispatcher->dispatch(contractExample('catalog.product-published.v1'), 'catalog.product-published');
    $cart = Cart::factory()->withLines(['TEE-MER-BLK-M' => 1])->create();

    $deactivated = contractExample('catalog.variant-changed.v1', ['name' => 'Black / Medium', 'active' => false, 'changedAt' => '2026-10-27T09:00:00Z'], ['id' => '01JBCDEFGHJKMNPQRSTVWXYZ40']);
    expect($dispatcher->dispatch($deactivated, 'catalog.variant-changed'))->toBe(DispatchResult::Handled);

    $this->getJson("/api/carts/{$cart->id}")
        ->assertOk()
        ->assertJsonPath('data.lines.0.variantName', 'Black / Medium')
        ->assertJsonPath('data.lines.0.available', false);
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 2])->assertUnprocessable()->assertJsonValidationErrors('sku');
});

it('ignores variant states older than the one it has', function (): void {
    $dispatcher = app(MessageDispatcher::class);
    $dispatcher->dispatch(contractExample('catalog.variant-changed.v1', ['active' => false, 'changedAt' => '2026-10-27T09:00:00Z']), 'catalog.variant-changed');

    $stale = contractExample('catalog.variant-changed.v1', ['name' => 'Old name', 'active' => true, 'changedAt' => '2026-10-26T12:00:00Z'], ['id' => '01JBCDEFGHJKMNPQRSTVWXYZ41']);
    expect($dispatcher->dispatch($stale, 'catalog.variant-changed'))->toBe(DispatchResult::Handled);

    $variant = CatalogVariant::query()->sole();
    expect($variant->active)->toBeFalse()
        ->and($variant->name)->toBe('Black / M');
});

it('replaces seeded rows with the real catalog ids, keyed by SKU', function (): void {
    CatalogVariant::query()->create(['id' => 'seed-tee-mer-blk-m', 'product_id' => 'seed-merino-crew-tee', 'sku' => 'TEE-MER-BLK-M', 'name' => 'Black / M', 'active' => true, 'changed_at' => '2000-01-01 00:00:00']);

    app(MessageDispatcher::class)->dispatch(contractExample('catalog.product-published.v1'), 'catalog.product-published');

    expect(CatalogVariant::query()->where('sku', 'TEE-MER-BLK-M')->sole()->only(['id', 'product_id']))
        ->toBe(['id' => '01JBCDEFGHJKMNPQRSTVWXYZ23', 'product_id' => '01JBCDEFGHJKMNPQRSTVWXYZ21']);
});
