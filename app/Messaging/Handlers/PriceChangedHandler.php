<?php

declare(strict_types=1);

namespace App\Messaging\Handlers;

use App\Models\SkuPrice;
use Carbon\CarbonImmutable;
use Shopwise\Contracts\Events\Pricing\PriceChangedV1;
use Shopwise\Kit\Messaging\Envelope;
use Shopwise\Kit\Messaging\MessageHandler;

/**
 * Keeps the last known unit price per SKU for display.
 */
final readonly class PriceChangedHandler implements MessageHandler
{
    public function handle(Envelope $envelope): void
    {
        $event = PriceChangedV1::fromArray($envelope->data);

        SkuPrice::apply($event->sku, $event->amount, $event->currency, CarbonImmutable::parse($event->changedAt)->utc());
    }
}
