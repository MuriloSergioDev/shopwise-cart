<?php

declare(strict_types=1);

namespace App\Messaging\Handlers;

use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use Carbon\CarbonImmutable;
use Shopwise\Contracts\Events\Catalog\ProductPublishedV1;
use Shopwise\Kit\Messaging\Envelope;
use Shopwise\Kit\Messaging\MessageHandler;

/**
 * Records the product as published and its (active) variants, which makes their SKUs sellable.
 */
final readonly class ProductPublishedHandler implements MessageHandler
{
    public function handle(Envelope $envelope): void
    {
        $event = ProductPublishedV1::fromArray($envelope->data);
        $publishedAt = CarbonImmutable::parse($event->publishedAt)->utc();

        CatalogProduct::query()->updateOrCreate(['id' => $event->productId], [
            'slug' => $event->slug,
            'name' => $event->name,
            'published_at' => $publishedAt,
        ]);

        /** @var list<array{variantId: string, sku: string, name: string}> $variants */
        $variants = $event->variants;
        foreach ($variants as $variant) {
            CatalogVariant::apply($variant['sku'], [
                'id' => $variant['variantId'],
                'product_id' => $event->productId,
                'name' => $variant['name'],
                'active' => true,
            ], $publishedAt);
        }
    }
}
