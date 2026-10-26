<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // JWT `sub` of the owner; null for a guest cart. One cart per customer.
            $table->string('customer_id', 64)->nullable()->unique();
            $table->string('coupon_code', 32)->nullable();
            $table->timestamps();

            // Guest cart pruning scans by age.
            $table->index('updated_at');
        });

        Schema::create('cart_lines', function (Blueprint $table): void {
            // Auto-increment id = order of first add.
            $table->id();
            $table->foreignUlid('cart_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64);
            $table->unsignedSmallInteger('quantity');

            $table->unique(['cart_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_lines');
        Schema::dropIfExists('carts');
    }
};
