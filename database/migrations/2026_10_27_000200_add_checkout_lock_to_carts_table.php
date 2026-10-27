<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            // The checkout that took the snapshot; while set, every change is 409 cart_locked (ADR-0004).
            $table->string('locked_by_checkout_id', 26)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropColumn('locked_by_checkout_id');
        });
    }
};
