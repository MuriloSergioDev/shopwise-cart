<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            // Increases on every change; checkout's snapshot carries it.
            $table->unsignedInteger('version')->default(0);
            // Set together when a completed checkout closes the cart (ADR-0004 step 7).
            $table->timestamp('checked_out_at')->nullable();
            $table->string('checkout_id', 26)->nullable();
            $table->string('order_id', 26)->nullable();

            // A customer keeps their closed carts and gets a new open one: only open carts are unique per customer.
            $table->dropUnique(['customer_id']);
            $table->index('customer_id');
        });

        // Partial index: Postgres and SQLite both support it, the schema builder does not.
        DB::statement('CREATE UNIQUE INDEX carts_open_customer_id_unique ON carts (customer_id) WHERE checked_out_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX carts_open_customer_id_unique');

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropIndex(['customer_id']);
            $table->unique('customer_id');
            $table->dropColumn(['version', 'checked_out_at', 'checkout_id', 'order_id']);
        });
    }
};
