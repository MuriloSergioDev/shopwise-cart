<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Read model of a published catalog product (from `catalog.product-published`). Display data only.
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property Carbon $published_at
 */
#[Fillable(['id', 'slug', 'name', 'published_at'])]
#[WithoutTimestamps]
#[WithoutIncrementing]
final class CatalogProduct extends Model
{
    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }
}
