<?php

declare(strict_types=1);

namespace App\Messaging\Handlers;

use App\Models\CatalogVariant;
use Carbon\CarbonImmutable;
use Shopwise\Contracts\Events\Catalog\VariantChangedV1;
use Shopwise\Kit\Messaging\Envelope;
use Shopwise\Kit\Messaging\MessageHandler;

/**
 * Upserts the variant's name and active flag. Draft variants are stored too; they become sellable once
 * their product is published.
 */
final readonly class VariantChangedHandler implements MessageHandler
{
    public function handle(Envelope $envelope): void
    {
        $event = VariantChangedV1::fromArray($envelope->data);

        CatalogVariant::apply($event->sku, [
            'id' => $event->variantId,
            'product_id' => $event->productId,
            'name' => $event->name,
            'active' => $event->active,
        ], CarbonImmutable::parse($event->changedAt)->utc());
    }
}
