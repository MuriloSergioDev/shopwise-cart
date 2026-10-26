<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\SkuPrice;
use Shopwise\Kit\Messaging\DispatchResult;
use Shopwise\Kit\Messaging\MessageDispatcher;

it('keeps the last known price per SKU, exactly once', function (): void {
    $envelope = contractExample('pricing.price-changed.v1');
    $dispatcher = app(MessageDispatcher::class);

    expect($dispatcher->dispatch($envelope, 'pricing.price-changed'))->toBe(DispatchResult::Handled)
        ->and($dispatcher->dispatch($envelope, 'pricing.price-changed'))->toBe(DispatchResult::Duplicate)
        ->and(SkuPrice::query()->sole()->only(['sku', 'unit_amount', 'currency']))->toBe(['sku' => 'TEE-MER-BLK-M', 'unit_amount' => 2900, 'currency' => 'USD']);

    $raised = contractExample('pricing.price-changed.v1', ['amount' => 3500, 'changedAt' => '2026-10-27T00:00:00Z'], ['id' => '01JBCDEFGHJKMNPQRSTVWXYZ50']);
    $stale = contractExample('pricing.price-changed.v1', ['amount' => 1000, 'changedAt' => '2026-10-26T06:00:00Z'], ['id' => '01JBCDEFGHJKMNPQRSTVWXYZ51']);
    $dispatcher->dispatch($raised, 'pricing.price-changed');
    $dispatcher->dispatch($stale, 'pricing.price-changed');

    expect(SkuPrice::query()->sole()->unit_amount)->toBe(3500);
});

it('shows the price on cart lines', function (): void {
    sellable('TEE-MER-BLK-M', unitAmount: null);
    $cart = Cart::factory()->withLines(['TEE-MER-BLK-M' => 1])->create();
    $this->getJson("/api/carts/{$cart->id}")->assertJsonPath('data.lines.0.unitAmount', null);

    app(MessageDispatcher::class)->dispatch(contractExample('pricing.price-changed.v1', ['amount' => 4200, 'currency' => 'EUR']), 'pricing.price-changed');

    $this->getJson("/api/carts/{$cart->id}")
        ->assertJsonPath('data.lines.0.unitAmount', 4200)
        ->assertJsonPath('data.lines.0.currency', 'EUR');
});
