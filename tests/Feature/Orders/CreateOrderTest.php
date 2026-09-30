<?php

declare(strict_types=1);

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use App\Models\Order;
use App\Models\User;

test('unauthenticated requests are rejected', function () {
    $this->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ])->assertUnauthorized();
});

test('an authenticated user can place an open buy order', function () {
    $user = User::factory()->funded('100000.00')->create();

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.symbol', 'BTC')
        ->assertJsonPath('data.side', 'buy')
        ->assertJsonPath('data.price', '95000.00')
        ->assertJsonPath('data.amount', '0.01000000')
        ->assertJsonPath('data.status', 'open');

    $order = Order::query()->sole();

    expect($order->id)->toBe($response->json('data.id'))
        ->and($order->user_id)->toBe($user->id)
        ->and($order->symbol)->toBe(Symbol::Btc)
        ->and($order->side)->toBe(OrderSide::Buy)
        ->and($order->status)->toBe(OrderStatus::Open);
});

test('eth buy orders are accepted', function () {
    $user = User::factory()->funded('100000.00')->create();

    $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'ETH',
        'side' => 'buy',
        'price' => '3000.00',
        'amount' => '1.50000000',
    ])->assertCreated()->assertJsonPath('data.symbol', 'ETH');
});

test('the notional is reserved and the fee is deducted upfront', function () {
    $user = User::factory()->funded('100000.00')->create();

    // 0.01 BTC at 95,000 USD = 950.00 notional; 1.5% fee = 14.25.
    $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ])->assertCreated();

    $user->refresh();

    expect($user->balance)->toBe('99035.75')
        ->and($user->locked_balance)->toBe('950.00');
});

test('fractional cents are rounded up for both the notional and the fee', function () {
    $user = User::factory()->funded('1000.00')->create();

    // 100.01 x 0.33333333 = 33.3333336633 -> 33.34 notional.
    // 33.3333336633 x 1.5% = 0.5000000049 -> 0.51 fee.
    $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '100.01',
        'amount' => '0.33333333',
    ])->assertCreated();

    $user->refresh();

    expect($user->locked_balance)->toBe('33.34')
        ->and($user->balance)->toBe('966.15');
});

test('insufficient funds including the fee leave balances and orders unchanged', function () {
    // Required is 964.25, so 964.24 is not enough.
    $user = User::factory()->funded('964.24')->create();

    $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ])->assertUnprocessable()->assertJsonValidationErrors('balance');

    $user->refresh();

    expect($user->balance)->toBe('964.24')
        ->and($user->locked_balance)->toBe('0.00')
        ->and(Order::query()->count())->toBe(0);
});

test('a balance of exactly the notional plus fee is accepted', function () {
    $user = User::factory()->funded('964.25')->create();

    $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ])->assertCreated();

    $user->refresh();

    expect($user->balance)->toBe('0.00')
        ->and($user->locked_balance)->toBe('950.00');
});

test('invalid orders are rejected without changing balances', function (array $payload) {
    $user = User::factory()->funded('100000.00')->create();

    $this->actingAs($user)->postJson('/api/orders', $payload)->assertUnprocessable();

    $user->refresh();

    expect(Order::query()->count())->toBe(0)
        ->and($user->balance)->toBe('100000.00')
        ->and($user->locked_balance)->toBe('0.00');
})->with([
    'unknown symbol' => [[
        'symbol' => 'DOGE',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ]],
    'sell side' => [[
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ]],
    'zero price' => [[
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '0',
        'amount' => '0.01000000',
    ]],
    'negative amount' => [[
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '-0.01000000',
    ]],
    'price with three decimals' => [[
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.001',
        'amount' => '0.01000000',
    ]],
    'amount with nine decimals' => [[
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.000000001',
    ]],
    'non numeric price' => [[
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => 'cheap',
        'amount' => '0.01000000',
    ]],
]);
