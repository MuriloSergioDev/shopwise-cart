<?php

declare(strict_types=1);
use App\Actions\SetCartItem;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use App\Models\SkuPrice;
use Illuminate\Support\Facades\DB;
use Shopwise\Kit\Messaging\MessageHandler;
use Shopwise\Kit\Messaging\Outbox;

arch()->preset()->php();
arch()->preset()->security();

arch('strict types and final classes in app code')
    ->expect('App')
    ->toUseStrictTypes()
    ->classes()->toBeFinal();

arch('controllers are thin invokables that delegate to actions')
    ->expect('App\Http\Controllers')
    ->toBeInvokable()
    ->not->toUse([DB::class, Outbox::class]);

arch('events are only recorded from actions and message handlers')
    ->expect(Outbox::class)
    ->toOnlyBeUsedIn(['App\Actions', 'App\Messaging\Handlers']);

arch('kafka is only touched through the kit')
    ->expect('App')
    ->not->toUse('Junges\Kafka');

arch('message handlers implement the kit contract')
    ->expect('App\Messaging\Handlers')
    ->toImplement(MessageHandler::class);

arch('DTOs are immutable')
    ->expect('App\Data')
    ->toBeReadonly();

arch('read models are fed by message handlers (and the demo seeder), never written over HTTP')
    ->expect([CatalogProduct::class, CatalogVariant::class, SkuPrice::class])
    ->toOnlyBeUsedIn(['App\Models', 'App\Messaging\Handlers', SetCartItem::class, 'Database\Seeders']);
