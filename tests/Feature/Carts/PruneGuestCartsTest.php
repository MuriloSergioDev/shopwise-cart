<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartLine;
use Illuminate\Console\Scheduling\Schedule;

it('prunes guest carts untouched for 30 days, with their lines', function (): void {
    $stale = Cart::factory()->withLines(['A' => 1])->create();
    $recentGuest = Cart::factory()->create();
    $staleCustomer = Cart::factory()->ownedBy(CUSTOMER_ID)->create();
    $stale->forceFill(['updated_at' => now()->subDays(31)])->saveQuietly();
    $staleCustomer->forceFill(['updated_at' => now()->subDays(90)])->saveQuietly();
    $recentGuest->forceFill(['updated_at' => now()->subDays(29)])->saveQuietly();

    $this->artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();

    expect(Cart::query()->pluck('id')->sort()->values()->all())->toBe(collect([$recentGuest->id, $staleCustomer->id])->sort()->values()->all())
        ->and(CartLine::query()->count())->toBe(0);
});

it('schedules the prune daily', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event): bool => str_contains((string) $event->command, 'model:prune'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});
