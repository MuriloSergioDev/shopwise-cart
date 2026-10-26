<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local copies of other services' data, written only by message handlers (and the demo seeder).
 * No foreign keys: events arrive in any order across topics, e.g. a draft's variants before its product.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A row exists only once catalog published the product.
        Schema::create('catalog_products', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('slug', 200);
            $table->string('name', 200);
            $table->timestamp('published_at');
        });

        Schema::create('catalog_variants', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('product_id', 64)->index();
            $table->string('sku', 64)->unique();
            $table->string('name', 200);
            $table->boolean('active');
            // Event time of the state stored here; older events are ignored.
            $table->timestamp('changed_at');
        });

        Schema::create('sku_prices', function (Blueprint $table): void {
            $table->string('sku', 64)->primary();
            $table->unsignedBigInteger('unit_amount');
            $table->char('currency', 3);
            $table->timestamp('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_prices');
        Schema::dropIfExists('catalog_variants');
        Schema::dropIfExists('catalog_products');
    }
};
