<?php

declare(strict_types=1);

use App\Messaging\Handlers\PriceChangedHandler;
use App\Messaging\Handlers\ProductPublishedHandler;
use App\Messaging\Handlers\VariantChangedHandler;

return [
    'source' => env('SERVICE_NAME', 'cart'),

    'consumer_group' => env('KAFKA_CONSUMER_GROUP_ID', env('SERVICE_NAME', 'cart')),

    // Event type => handler. Every type listed here needs its topic in deploy/values.yaml → consumers.
    // All three feed local read models (display data and "for sale"); cart never calls catalog or pricing.
    'handlers' => [
        'catalog.product-published' => ProductPublishedHandler::class,
        'catalog.variant-changed' => VariantChangedHandler::class,
        'pricing.price-changed' => PriceChangedHandler::class,
    ],

    'max_attempts' => 3,

    'relay_batch_size' => 100,
];
