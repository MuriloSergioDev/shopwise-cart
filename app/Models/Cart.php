<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\CartLocked;
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
 * A guest cart (customer_id null, reachable by id alone) or a customer's cart (customer_id = JWT `sub`). A completed
 * checkout closes a customer cart (`checked_out_at`); a closed cart is 404 everywhere and the customer gets a new one.
 *
 * @property string $id
 * @property string|null $customer_id
 * @property string|null $coupon_code
 * @property int $version
 * @property Carbon|null $checked_out_at
 * @property string|null $checkout_id
 * @property string|null $order_id
 * @property string|null $locked_by_checkout_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CartLine> $lines
 */
#[Fillable(['customer_id', 'coupon_code', 'locked_by_checkout_id'])]
final class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    use HasUlids;
    use MassPrunable;

    /**
     * Column defaults, so a cart created in this request renders without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['version' => 0, 'locked_by_checkout_id' => null];

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

    public function isCheckedOut(): bool
    {
        return $this->checked_out_at !== null;
    }

    /**
     * Refuses a change while a checkout holds the cart's lock. Call it on a row locked for update.
     *
     * @throws CartLocked
     */
    public function ensureUnlocked(): void
    {
        if ($this->locked_by_checkout_id !== null) {
            throw new CartLocked($this->locked_by_checkout_id);
        }
    }

    /**
     * Saves a change to the cart: bumps `version` in the database (so concurrent writers never share one) and
     * `updated_at`, together with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordChange(array $attributes = []): void
    {
        $this->increment('version', 1, [...$attributes, 'updated_at' => $this->freshTimestamp()]);
    }

    /**
     * Loads what CartResource renders: lines with their read-model names, availability and prices.
     */
    public function loadForDisplay(): self
    {
        return $this->load(['lines.variant.product', 'lines.price']);
    }

    /**
     * Carts that were not checked out yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('checked_out_at');
    }

    /**
     * Carts the caller may see: every guest cart, plus their own open customer cart. Anything else looks unknown (404).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function accessibleBy(Builder $query, ?JwtUser $user): void
    {
        $query->whereNull('checked_out_at')->where(function (Builder $query) use ($user): void {
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

    protected function casts(): array
    {
        return ['version' => 'integer', 'checked_out_at' => 'datetime'];
    }
}
