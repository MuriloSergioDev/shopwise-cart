<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $cart_id
 * @property string $sku
 * @property int $quantity
 * @property-read CatalogVariant|null $variant
 * @property-read SkuPrice|null $price
 */
#[Fillable(['sku', 'quantity'])]
#[WithoutTimestamps]
final class CartLine extends Model
{
    /**
     * @return BelongsTo<CatalogVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'sku', 'sku');
    }

    /**
     * @return BelongsTo<SkuPrice, $this>
     */
    public function price(): BelongsTo
    {
        return $this->belongsTo(SkuPrice::class, 'sku', 'sku');
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
