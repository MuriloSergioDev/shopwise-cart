<?php

declare(strict_types=1);

use App\Models\Cart;
use Illuminate\Support\Facades\Date;
use Shopwise\Contracts\Events\Cart\CartCheckedOutV1;
use Shopwise\Kit\Messaging\OutboxMessage;

const CHECKOUT_ID = '01JBCDEFGHJKMNPQRSTVWXYZ40';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function checkedOutBody(array $overrides = []): array
{
    return [
        'checkoutId' => CHECKOUT_ID,
        'orderId' => '01JBCDEFGHJKMNPQRSTVWXYZ44',
        'lines' => [['sku' => 'TEE-MER-BLK-M', 'quantity' => 2]],
        ...$overrides,
    ];
}

it('closes the cart and records cart.cart-checked-out with the snapshot\'s lines', function (): void {
    Date::setTestNow('2026-10-26T12:01:25Z');
    // A line that is not in the snapshot's lines is dropped with the cart.
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->withLines(['TEE-MER-BLK-M' => 2, 'MUG-BLK' => 1])->create();

    $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody())->assertOk()->assertExactJson(['data' => [
        'cartId' => $cart->id,
        'checkoutId' => CHECKOUT_ID,
        'orderId' => '01JBCDEFGHJKMNPQRSTVWXYZ44',
        'closedAt' => '2026-10-26T12:01:25Z',
    ]]);

    $cart->refresh();
    expect($cart->isCheckedOut())->toBeTrue()
        ->and($cart->checkout_id)->toBe(CHECKOUT_ID)
        ->and($cart->order_id)->toBe('01JBCDEFGHJKMNPQRSTVWXYZ44')
        ->and($cart->version)->toBe(1);

    $message = OutboxMessage::query()->sole();
    expect($message->type)->toBe(CartCheckedOutV1::TYPE)
        ->and($message->subject)->toBe($cart->id)
        ->and($message->envelope['dataschema'])->toBe('cart/cart-checked-out.v1')
        ->and($message->envelope['data'])->toEqualCanonicalizing([
            'cartId' => $cart->id,
            'customerId' => CUSTOMER_ID,
            'checkoutId' => CHECKOUT_ID,
            'orderId' => '01JBCDEFGHJKMNPQRSTVWXYZ44',
            'lines' => [['sku' => 'TEE-MER-BLK-M', 'quantity' => 2]],
            'checkedOutAt' => '2026-10-26T12:01:25Z',
        ]);
});

it('is idempotent per checkout: a repeat is 200 with the same body and no second event', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();

    $first = $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody())->assertOk();
    $this->travel(5)->minutes();
    $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody())->assertOk()->assertExactJson($first->json());

    expect(OutboxMessage::query()->count())->toBe(1)
        ->and($cart->refresh()->version)->toBe(1);
});

it('refuses to close a cart again for a different checkout', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();
    $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody())->assertOk();

    $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody(['checkoutId' => '01JBCDEFGHJKMNPQRSTVWXYZ41']))->assertConflict();

    expect(OutboxMessage::query()->count())->toBe(1)
        ->and($cart->refresh()->checkout_id)->toBe(CHECKOUT_ID);
});

it('404s an unknown or guest cart', function (): void {
    $guest = Cart::factory()->create();

    $this->postJson('/internal/carts/unknown/checked-out', checkedOutBody())->assertNotFound();
    $this->postJson("/internal/carts/{$guest->id}/checked-out", checkedOutBody())->assertNotFound();
    expect(OutboxMessage::query()->count())->toBe(0);
});

it('validates the request', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();

    $this->postJson("/internal/carts/{$cart->id}/checked-out", ['checkoutId' => str_repeat('x', 27), 'lines' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['checkoutId', 'orderId', 'lines']);
    $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody(['lines' => [['sku' => 'A', 'quantity' => 100], ['sku' => 'A', 'quantity' => 1]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.quantity', 'lines.0.sku']);

    expect($cart->refresh()->isCheckedOut())->toBeFalse();
});

it('hides a closed cart everywhere and blocks further edits', function (): void {
    sellable('TEE-MER-BLK-M');
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->withLines(['TEE-MER-BLK-M' => 2])->create();
    $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody())->assertOk();

    $this->getJson("/api/carts/{$cart->id}", bearer())->assertNotFound();
    $this->putJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", ['quantity' => 5], bearer())->assertNotFound();
    $this->deleteJson("/api/carts/{$cart->id}/items/TEE-MER-BLK-M", [], bearer())->assertNotFound();
    $this->putJson("/api/carts/{$cart->id}/coupon", ['code' => 'SAVE5'], bearer())->assertNotFound();
    $this->deleteJson("/api/carts/{$cart->id}/coupon", [], bearer())->assertNotFound();

    expect($cart->lines()->sole()->quantity)->toBe(2)
        ->and($cart->refresh()->coupon_code)->toBeNull();
});

it('gives the customer a fresh cart after checkout, and merges guest carts into that one', function (): void {
    sellable('MUG-BLK');
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->withLines(['TEE-MER-BLK-M' => 2])->create();
    $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody())->assertOk();

    $fresh = $this->getJson('/api/me/cart', bearer())->assertCreated()->assertJsonPath('data.lines', []);
    expect($fresh->json('data.id'))->not->toBe($cart->id);

    $guest = Cart::factory()->withLines(['MUG-BLK' => 1])->create();
    $this->postJson('/api/me/cart/merge', ['guestCartId' => $guest->id], bearer())
        ->assertOk()
        ->assertJsonPath('data.id', $fresh->json('data.id'))
        ->assertJsonPath('data.lines.0.sku', 'MUG-BLK');

    expect(Cart::query()->where('customer_id', CUSTOMER_ID)->count())->toBe(2);
});

it('takes no access token: internal routes are cluster-only', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create();

    // Even a bad bearer header is ignored rather than rejected.
    $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody(), ['Authorization' => 'Bearer nonsense'])->assertOk();
});

it('closes a cart locked by the same checkout and ends the lock, but refuses another checkout\'s lock', function (): void {
    $cart = Cart::factory()->ownedBy(CUSTOMER_ID)->create(['locked_by_checkout_id' => CHECKOUT_ID]);
    $other = Cart::factory()->ownedBy('01JBCDEFGHJKMNPQRSTVWXYZ55')->create(['locked_by_checkout_id' => 'ANOTHER-CHECKOUT']);

    $this->postJson("/internal/carts/{$cart->id}/checked-out", checkedOutBody())->assertOk();
    $this->postJson("/internal/carts/{$other->id}/checked-out", checkedOutBody())->assertConflict();

    expect($cart->refresh()->locked_by_checkout_id)->toBeNull()
        ->and($other->refresh()->isCheckedOut())->toBeFalse()
        ->and(OutboxMessage::query()->count())->toBe(1);
});
