<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Shopwise\Kit\Auth\JwtUser;

/**
 * A guest cart (customer_id null, reachable by id alone) or a customer's cart (customer_id = JWT `sub`).
 *
 * @property string $id
 * @property string|null $customer_id
 * @property string|null $coupon_code
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CartLine> $lines
 */
#[Fillable(['customer_id', 'coupon_code'])]
final class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    use HasUlids;
    use MassPrunable;

    public const int MAX_LINES = 50;

    public const int MAX_QUANTITY = 99;

    public const int GUEST_TTL_DAYS = 30;

    /**
     * @return HasMany<CartLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CartLine::class)->orderBy('id');
    }

    public function isGuest(): bool
    {
        return $this->customer_id === null;
    }

    /**
     * Loads what CartResource renders: lines with their read-model names, availability and prices.
     */
    public function loadForDisplay(): self
    {
        return $this->load(['lines.variant.product', 'lines.price']);
    }

    /**
     * Carts the caller may see: every guest cart, plus their own customer cart. Anything else looks unknown (404).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function accessibleBy(Builder $query, ?JwtUser $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->whereNull('customer_id');
            if ($user instanceof JwtUser) {
                $query->orWhere('customer_id', $user->id);
            }
        });
    }

    /**
     * Guest carts untouched for GUEST_TTL_DAYS (`model:prune`, scheduled daily). Lines go with them (FK cascade).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->whereNull('customer_id')->where('updated_at', '<', now()->subDays(self::GUEST_TTL_DAYS));
    }
}
