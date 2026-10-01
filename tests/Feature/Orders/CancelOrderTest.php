<?php

declare(strict_types=1);

use App\Enums\Symbol;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;

test('unauthenticated requests are rejected', function () {
    $order = Order::factory()->create();

    $this->postJson("/api/orders/{$order->id}/cancel")->assertUnauthorized();
});

test('cancelling an open buy refunds the notional and the upfront fee', function () {
    $user = User::factory()->funded('100000.00')->create();

    $orderId = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'buy-to-cancel'])->json('data.id');

    $user->refresh();
    expect($user->balance)->toBe('99035.75')
        ->and($user->locked_balance)->toBe('964.25');

    $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.id', $orderId)
        ->assertJsonPath('data.status', 'cancelled');

    $user->refresh();

    expect($user->balance)->toBe('100000.00')
        ->and($user->locked_balance)->toBe('0.00')
        ->and(Order::query()->sole()->status->value)->toBe('cancelled');
});

test('cancelling an open sell releases the locked asset amount', function () {
    $user = User::factory()->funded('1000.00')->create();
    Asset::factory()->for($user)->create(['symbol' => Symbol::Btc, 'amount' => '0.50000000']);

    $orderId = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'sell-to-cancel'])->json('data.id');

    $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $asset = Asset::query()->where('user_id', $user->id)->where('symbol', Symbol::Btc)->sole();

    expect($asset->amount)->toBe('0.50000000')
        ->and($asset->locked_amount)->toBe('0.00000000')
        ->and(Order::query()->sole()->status->value)->toBe('cancelled');
});

test('cancelling an open sell twice releases the locked asset only once', function () {
    $user = User::factory()->funded('1000.00')->create();
    Asset::factory()->for($user)->create(['symbol' => Symbol::Btc, 'amount' => '0.50000000']);

    $orderId = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'double-cancel-sell'])->json('data.id');

    $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel")->assertStatus(409);

    $asset = Asset::query()->where('user_id', $user->id)->where('symbol', Symbol::Btc)->sole();

    expect($asset->amount)->toBe('0.50000000')
        ->and($asset->locked_amount)->toBe('0.00000000')
        ->and(Order::query()->sole()->status->value)->toBe('cancelled');
});

test('cancelling a buy whose notional and fee were rounded up restores the exact balance', function () {
    $user = User::factory()->funded('1000.00')->create();

    // 100.01 x 0.33333333 = 33.3333336633 -> 33.34 notional; fee 0.51.
    $orderId = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '100.01',
        'amount' => '0.33333333',
    ], ['Idempotency-Key' => 'rounded-to-cancel'])->json('data.id');

    $user->refresh();
    expect($user->balance)->toBe('966.15')
        ->and($user->locked_balance)->toBe('33.85');

    $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel")->assertOk();

    $user->refresh();

    expect($user->balance)->toBe('1000.00')
        ->and($user->locked_balance)->toBe('0.00');
});

test('cancelling an order twice returns a conflict without changing balances', function () {
    $user = User::factory()->funded('1000.00')->create();

    $orderId = $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'double-cancel'])->json('data.id');

    $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel")->assertOk();

    $user->refresh();
    $balance = $user->balance;
    $locked = $user->locked_balance;

    $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel")->assertStatus(409);

    $user->refresh();

    expect($user->balance)->toBe($balance)
        ->and($user->locked_balance)->toBe($locked)
        ->and(Order::query()->sole()->status->value)->toBe('cancelled');
});

test('cancelling a filled order returns a conflict without changing balances', function () {
    $user = User::factory()->funded('1000.00')->create();
    $order = Order::factory()->for($user)->filled()->create();

    $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel")->assertStatus(409);

    expect($user->refresh()->balance)->toBe('1000.00')
        ->and($order->refresh()->status->value)->toBe('filled');
});

test('cancelling another users order returns not found without changing balances', function () {
    $owner = User::factory()->funded('1000.00')->create();
    $order = Order::factory()->for($owner)->create();

    $caller = User::factory()->funded('500.00')->create();

    $this->actingAs($caller)->postJson("/api/orders/{$order->id}/cancel")->assertNotFound();

    expect($caller->refresh()->balance)->toBe('500.00')
        ->and($order->refresh()->status->value)->toBe('open');
});

test('cancelling a missing order returns not found', function () {
    $user = User::factory()->funded('1000.00')->create();

    $this->actingAs($user)->postJson('/api/orders/999999/cancel')->assertNotFound();
});

test('cancelling with a non numeric id returns not found', function () {
    $user = User::factory()->funded('1000.00')->create();

    $this->actingAs($user)->postJson('/api/orders/abc/cancel')->assertNotFound();
});

test('cancelling a sell whose reserved asset balance is missing returns a conflict', function () {
    $user = User::factory()->funded('1000.00')->create();
    $order = Order::factory()->for($user)->sell()->create();

    $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel")->assertStatus(409);

    expect($order->refresh()->status->value)->toBe('open');
});
