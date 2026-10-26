<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read model of a catalog variant (from `catalog.variant-changed` and `catalog.product-published`).
 *
 * @property string $id
 * @property string $product_id
 * @property string $sku
 * @property string $name
 * @property bool $active
 * @property CarbonImmutable $changed_at
 * @property-read CatalogProduct|null $product
 */
#[Fillable(['id', 'product_id', 'sku', 'name', 'active', 'changed_at'])]
#[WithoutTimestamps]
#[WithoutIncrementing]
final class CatalogVariant extends Model
{
    protected $keyType = 'string';

    /**
     * Set only once the product is published.
     *
     * @return BelongsTo<CatalogProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'product_id');
    }

    /**
     * For sale = the variant is active and its product has been published. Needs `product` loaded.
     */
    public function isForSale(): bool
    {
        return $this->active && $this->product instanceof CatalogProduct;
    }

    /**
     * Upserts the variant's state unless a newer event already wrote it (redeliveries and cross-topic reordering).
     * Keyed by SKU (catalog never reuses or changes one), so events replace the demo seeder's rows, which carry
     * made-up ids and an old changed_at.
     *
     * @param  array{id: string, product_id: string, name: string, active: bool}  $state
     */
    public static function apply(string $sku, array $state, CarbonImmutable $changedAt): void
    {
        $existing = self::query()->lockForUpdate()->firstWhere('sku', $sku);
        if ($existing instanceof self && $existing->changed_at->greaterThan($changedAt)) {
            return;
        }

        self::query()->updateOrCreate(['sku' => $sku], [...$state, 'changed_at' => $changedAt]);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'changed_at' => 'immutable_datetime'];
    }
}
