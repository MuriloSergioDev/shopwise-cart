<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use App\Models\SkuPrice;
use Database\Seeders\DatabaseSeeder;

it('seeds catalog\'s demo SKUs with USD prices once', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(CatalogProduct::query()->count())->toBe(7)
        ->and(CatalogVariant::query()->count())->toBe(28)
        ->and(SkuPrice::query()->count())->toBe(28)
        ->and(SkuPrice::query()->where('currency', '!=', 'USD')->count())->toBe(0)
        ->and(CatalogVariant::query()->whereIn('sku', ['TEE-MER-BLA-M', 'SKL-CST', 'COF-POUR-WHI', 'JEA-SEL-IND-32'])->count())->toBe(4);

    $cart = Cart::factory()->create();
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLA-M", ['quantity' => 1])
        ->assertOk()
        ->assertJsonPath('data.lines.0.productSlug', 'merino-crew-tee')
        ->assertJsonPath('data.lines.0.variantName', 'Black / M')
        ->assertJsonPath('data.lines.0.unitAmount', 2900)
        ->assertJsonPath('data.lines.0.currency', 'USD');
});
