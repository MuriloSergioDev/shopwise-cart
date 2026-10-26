<?php

declare(strict_types=1);

use App\Models\Cart;
use Illuminate\Support\Facades\Schedule;

// Deletes guest carts untouched for 30 days (Cart::prunable()).
Schedule::command('model:prune', ['--model' => [Cart::class]])->daily();
