<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Last known unit price of a SKU (from `pricing.price-changed`). Display only: totals come from pricing quotes.
 *
 * @property string $sku
 * @property int $unit_amount
 * @property string $currency
 * @property CarbonImmutable $changed_at
 */
#[Fillable(['sku', 'unit_amount', 'currency', 'changed_at'])]
#[WithoutTimestamps]
#[WithoutIncrementing]
final class SkuPrice extends Model
{
    protected $primaryKey = 'sku';

    protected $keyType = 'string';

    /**
     * Upserts the price unless a newer event already wrote it.
     */
    public static function apply(string $sku, int $unitAmount, string $currency, CarbonImmutable $changedAt): void
    {
        $existing = self::query()->lockForUpdate()->find($sku);
        if ($existing instanceof self && $existing->changed_at->greaterThan($changedAt)) {
            return;
        }

        self::query()->updateOrCreate(['sku' => $sku], ['unit_amount' => $unitAmount, 'currency' => $currency, 'changed_at' => $changedAt]);
    }

    protected function casts(): array
    {
        return ['unit_amount' => 'integer', 'changed_at' => 'immutable_datetime'];
    }
}
