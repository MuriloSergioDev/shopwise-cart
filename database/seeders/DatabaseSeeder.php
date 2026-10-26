<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use App\Models\SkuPrice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Fills the read models with shopwise-catalog's demo products (same slugs, names and SKUs as its DatabaseSeeder)
 * and USD demo prices, so a fresh cluster has a working cart without replaying Kafka. Product/variant ids are
 * made up and changed_at is old: real catalog/pricing events replace these rows (variants are keyed by SKU).
 * Runs once: a second run is a no-op.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Name, SKU prefix, colors, sizes and unit price in cents. Mirrors shopwise-catalog's demo catalog.
     *
     * @var list<array{string, string, list<string>, list<string>, int}>
     */
    private const array PRODUCTS = [
        ['Merino Crew Tee', 'TEE-MER', ['Black', 'Navy'], ['S', 'M', 'L'], 2900],
        ['Linen Camp Shirt', 'SHI-LIN', ['White', 'Sage'], ['M', 'L'], 5900],
        ['Heavyweight Hoodie', 'HOO-HVY', ['Grey', 'Black'], ['S', 'M', 'L', 'XL'], 6900],
        ['Selvedge Denim Jeans', 'JEA-SEL', ['Indigo'], ['30', '32', '34'], 12900],
        ['Cotton Chino Shorts', 'SHO-CHI', ['Khaki', 'Navy'], ['30', '32'], 3900],
        ['Cast Iron Skillet', 'SKL-CST', [], [], 4500],
        ['Ceramic Pour-Over Coffee Set', 'COF-POUR', ['White', 'Charcoal'], [], 3800],
    ];

    public function run(): void
    {
        if (CatalogProduct::query()->exists()) {
            return;
        }

        $seededAt = CarbonImmutable::parse('2000-01-01T00:00:00Z');

        foreach (self::PRODUCTS as [$name, $skuPrefix, $colors, $sizes, $unitAmount]) {
            $slug = Str::slug($name);
            $product = CatalogProduct::query()->create(['id' => "seed-{$slug}", 'slug' => $slug, 'name' => $name, 'published_at' => $seededAt]);

            foreach ($this->variants($skuPrefix, $colors, $sizes) as $sku => $variantName) {
                CatalogVariant::query()->create([
                    'id' => 'seed-'.mb_strtolower($sku),
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'name' => $variantName,
                    'active' => true,
                    'changed_at' => $seededAt,
                ]);
                SkuPrice::query()->create(['sku' => $sku, 'unit_amount' => $unitAmount, 'currency' => 'USD', 'changed_at' => $seededAt]);
            }
        }
    }

    /**
     * Every color × size combination, named and coded like catalog's seeder; no options gives "Standard".
     *
     * @param  list<string>  $colors
     * @param  list<string>  $sizes
     * @return array<string, string> SKU => variant name
     */
    private function variants(string $skuPrefix, array $colors, array $sizes): array
    {
        $variants = [];
        foreach ($colors === [] ? [null] : $colors as $color) {
            foreach ($sizes === [] ? [null] : $sizes as $size) {
                $options = array_filter([$color, $size], fn (?string $value): bool => $value !== null);
                $sku = implode('-', [$skuPrefix, ...array_map(fn (string $value): string => mb_strtoupper(mb_substr($value, 0, 3)), $options)]);
                $variants[$sku] = $options === [] ? 'Standard' : implode(' / ', $options);
            }
        }

        return $variants;
    }
}
