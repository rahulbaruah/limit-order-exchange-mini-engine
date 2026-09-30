<?php

declare(strict_types=1);

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('creates the exchange tables', function () {
    expect(Schema::hasTable('assets'))->toBeTrue()
        ->and(Schema::hasTable('orders'))->toBeTrue()
        ->and(Schema::hasTable('trades'))->toBeTrue();
});

it('adds usd balance columns to users', function () {
    expect(Schema::hasColumns('users', ['balance', 'locked_balance']))->toBeTrue();
});

it('stores usd balances with decimal precision', function () {
    $user = User::factory()->create([
        'balance' => '950.25',
        'locked_balance' => '10.00',
    ]);

    $user->refresh();

    expect($user->balance)->toBe('950.25')
        ->and($user->locked_balance)->toBe('10.00');
});

it('allows only one asset row per user and symbol', function () {
    $user = User::factory()->create();

    Asset::factory()->for($user)->create(['symbol' => Symbol::Btc]);

    expect(fn () => Asset::factory()->for($user)->create(['symbol' => Symbol::Btc]))
        ->toThrow(QueryException::class);
});

it('allows the same symbol to be held by different users', function () {
    Asset::factory()->create(['symbol' => Symbol::Btc]);
    Asset::factory()->create(['symbol' => Symbol::Btc]);

    expect(Asset::where('symbol', Symbol::Btc)->count())->toBe(2);
});

it('casts order enums and decimal values', function () {
    $order = Order::factory()->create([
        'side' => OrderSide::Sell,
        'status' => OrderStatus::Open,
        'price' => '95000.00',
        'amount' => '0.01000000',
    ]);

    $order->refresh();

    expect($order->side)->toBe(OrderSide::Sell)
        ->and($order->status)->toBe(OrderStatus::Open)
        ->and($order->symbol)->toBe(Symbol::Btc)
        ->and($order->price)->toBe('95000.00')
        ->and($order->amount)->toBe('0.01000000');
});

it('records a trade with only a created_at timestamp', function () {
    $trade = Trade::factory()->create();

    expect($trade->created_at)->not->toBeNull()
        ->and($trade->updated_at)->toBeNull()
        ->and($trade->symbol)->toBe(Symbol::Btc)
        ->and($trade->gross_amount)->toBe('950.00')
        ->and($trade->fee)->toBe('14.25');
});

it('cascades asset balances when a user is deleted', function () {
    $user = User::factory()->create();
    Asset::factory()->for($user)->create();

    $user->delete();

    expect(Asset::count())->toBe(0);
});

it('cascades orders when a user is deleted', function () {
    $user = User::factory()->create();
    Order::factory()->for($user)->create();

    $user->delete();

    expect(Order::count())->toBe(0);
});
