<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\Symbol;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Brick\Math\BigDecimal;

/**
 * Create a user holding an open sell order with the asset amount already locked.
 *
 * @return array{0: User, 1: Order}
 */
function restingSell(string $price, string $amount = '0.01000000', Symbol $symbol = Symbol::Btc): array
{
    $seller = User::factory()->funded('0.00')->create();

    Asset::factory()->locked($amount)->create([
        'user_id' => $seller->id,
        'symbol' => $symbol,
        'amount' => '0.00000000',
    ]);

    $order = Order::factory()->sell()->forSymbol($symbol)->create([
        'user_id' => $seller->id,
        'price' => $price,
        'amount' => $amount,
    ]);

    return [$seller, $order];
}

test('a buy order matches a cheaper resting sell order', function () {
    [$seller, $sellOrder] = restingSell('94000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'buy-matches-sell']);

    $response->assertCreated()->assertJsonPath('data.status', 'filled');

    expect($sellOrder->refresh()->status)->toBe(OrderStatus::Filled);

    $trade = Trade::query()->sole();

    expect($trade->buy_order_id)->toBe($response->json('data.id'))
        ->and($trade->sell_order_id)->toBe($sellOrder->id)
        ->and($trade->buyer_id)->toBe($buyer->id)
        ->and($trade->seller_id)->toBe($seller->id)
        ->and($trade->symbol)->toBe(Symbol::Btc)
        ->and($trade->price)->toBe('94000.00')
        ->and($trade->amount)->toBe('0.01000000')
        ->and($trade->gross_amount)->toBe('940.00')
        ->and($trade->fee)->toBe('14.10');
});

test('a buy order matches a resting sell order at exactly the same price', function () {
    [, $sellOrder] = restingSell('95000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $response = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'exact-price-match']);

    $response->assertCreated()->assertJsonPath('data.status', 'filled');

    $trade = Trade::query()->sole();

    expect($sellOrder->refresh()->status)->toBe(OrderStatus::Filled)
        ->and($trade->price)->toBe('95000.00')
        ->and($trade->amount)->toBe('0.01000000')
        ->and($trade->gross_amount)->toBe('950.00')
        ->and($trade->fee)->toBe('14.25');

    $buyer->refresh();

    expect($buyer->balance)->toBe('99035.75')
        ->and($buyer->locked_balance)->toBe('0.00')
        ->and($buyer->assets()->sole()->amount)->toBe('0.01000000');
});

test('a matched buy is settled at the resting price and the overcharge is refunded', function () {
    restingSell('94000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'buy-settlement'])->assertCreated();

    // Reserved 950.00 + 14.25 fee, spent 940.00 + 14.10, so 10.15 comes back.
    expect($buyer->refresh()->balance)->toBe('99045.90')
        ->and($buyer->locked_balance)->toBe('0.00');
});

test('a matched trade credits the seller and consumes the locked asset', function () {
    [$seller] = restingSell('94000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'seller-settlement'])->assertCreated();

    $sellerAsset = $seller->assets()->sole();

    expect($seller->refresh()->balance)->toBe('940.00')
        ->and($seller->locked_balance)->toBe('0.00')
        ->and($sellerAsset->amount)->toBe('0.00000000')
        ->and($sellerAsset->locked_amount)->toBe('0.00000000');
});

test('a matched buy creates the asset balance when the buyer holds none', function () {
    restingSell('94000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'asset-created'])->assertCreated();

    $asset = $buyer->assets()->sole();

    expect($asset->symbol)->toBe(Symbol::Btc)
        ->and($asset->amount)->toBe('0.01000000')
        ->and($asset->locked_amount)->toBe('0.00000000');
});

test('a matched buy adds to an existing asset balance', function () {
    restingSell('94000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    Asset::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => Symbol::Btc,
        'amount' => '2.50000000',
    ]);

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'asset-incremented'])->assertCreated();

    expect($buyer->assets()->sole()->amount)->toBe('2.51000000');
});

test('a sell order matches a resting buy order at the buy price', function () {
    $buyer = User::factory()->funded('99035.75')->create(['locked_balance' => '964.25']);

    $buyOrder = Order::factory()->buy()->create([
        'user_id' => $buyer->id,
        'price' => '95000.00',
        'amount' => '0.01000000',
    ]);

    $seller = User::factory()->funded('0.00')->create();
    Asset::factory()->create(['user_id' => $seller->id, 'amount' => '1.00000000']);

    $response = $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '94000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'sell-matches-buy']);

    $response->assertCreated()->assertJsonPath('data.status', 'filled');

    $trade = Trade::query()->sole();

    expect($buyOrder->refresh()->status)->toBe(OrderStatus::Filled)
        ->and($trade->price)->toBe('95000.00')
        ->and($trade->gross_amount)->toBe('950.00')
        ->and($trade->fee)->toBe('14.25')
        ->and($seller->refresh()->balance)->toBe('950.00')
        ->and($buyer->refresh()->balance)->toBe('99035.75')
        ->and($buyer->locked_balance)->toBe('0.00')
        ->and($buyer->assets()->sole()->amount)->toBe('0.01000000');

    $sellerAsset = $seller->assets()->sole();

    expect($sellerAsset->amount)->toBe('0.99000000')
        ->and($sellerAsset->locked_amount)->toBe('0.00000000');
});

test('a resting buy order is matched when a sell order arrives through the api', function () {
    $buyer = User::factory()->funded('100000.00')->create();
    $seller = User::factory()->funded('0.00')->create();

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => Symbol::Btc,
        'amount' => '1.00000000',
    ]);

    $buyId = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'resting-buy'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->json('data.id');

    $buyer->refresh();

    expect($buyer->balance)->toBe('99035.75')
        ->and($buyer->locked_balance)->toBe('964.25');

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '94000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'arriving-sell'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'filled');

    // The execution price is the resting counter order's price, not the arriving sell's.
    $trade = Trade::query()->sole();

    expect($trade->buy_order_id)->toBe($buyId)
        ->and($trade->price)->toBe('95000.00')
        ->and($trade->gross_amount)->toBe('950.00')
        ->and($trade->fee)->toBe('14.25')
        ->and(Order::query()->findOrFail($buyId)->status)->toBe(OrderStatus::Filled);

    $sellerAsset = $seller->refresh()->assets()->sole();
    $buyer->refresh();

    expect($seller->balance)->toBe('950.00')
        ->and($sellerAsset->amount)->toBe('0.99000000')
        ->and($sellerAsset->locked_amount)->toBe('0.00000000')
        ->and($buyer->balance)->toBe('99035.75')
        ->and($buyer->locked_balance)->toBe('0.00')
        ->and($buyer->assets()->sole()->amount)->toBe('0.01000000');
});

test('the cheapest resting sell is matched first', function () {
    [, $expensive] = restingSell('94000.00');
    [, $cheapest] = restingSell('93000.00');

    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'price-priority'])->assertCreated();

    expect($cheapest->refresh()->status)->toBe(OrderStatus::Filled)
        ->and($expensive->refresh()->status)->toBe(OrderStatus::Open);
});

test('the earliest resting sell is matched first at an equal price', function () {
    [, $earliest] = restingSell('94000.00');
    [, $latest] = restingSell('94000.00');

    $earliest->forceFill(['created_at' => now()->subHour()])->save();

    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'time-priority'])->assertCreated();

    expect($earliest->refresh()->status)->toBe(OrderStatus::Filled)
        ->and($latest->refresh()->status)->toBe(OrderStatus::Open);
});

test('the highest resting buy is matched first', function () {
    $makeBuy = function (string $price, string $lockedBalance): Order {
        $buyer = User::factory()->funded('100000.00')->create(['locked_balance' => $lockedBalance]);

        return Order::factory()->buy()->create([
            'user_id' => $buyer->id,
            'price' => $price,
            'amount' => '0.01000000',
        ]);
    };

    $lower = $makeBuy('95000.00', '964.25');
    $higher = $makeBuy('96000.00', '974.40');

    $seller = User::factory()->funded('0.00')->create();
    Asset::factory()->create(['user_id' => $seller->id, 'amount' => '1.00000000']);

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '94000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'buy-price-priority'])->assertCreated();

    expect($higher->refresh()->status)->toBe(OrderStatus::Filled)
        ->and($lower->refresh()->status)->toBe(OrderStatus::Open);
});

test('an order that cannot be matched stays open', function (array $restingOverrides, array $payload) {
    $seller = User::factory()->funded('0.00')->create();

    Asset::factory()->locked('0.01000000')->create([
        'user_id' => $seller->id,
        'symbol' => $restingOverrides['symbol'] ?? Symbol::Btc,
    ]);

    $sellOrder = Order::factory()->sell()->create([
        'user_id' => $seller->id,
        'price' => '94000.00',
        'amount' => '0.01000000',
        ...$restingOverrides,
    ]);

    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
        ...$payload,
    ], ['Idempotency-Key' => 'no-match'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');

    expect($sellOrder->refresh()->status)->toBe($restingOverrides['status'] ?? OrderStatus::Open)
        ->and(Trade::query()->count())->toBe(0);

    // An unmatched buy still reserves its notional and fee.
    $buyer->refresh();

    expect($buyer->balance)->toBe('99035.75')
        ->and($buyer->locked_balance)->toBe('964.25');
})->with([
    'the sell is priced above the buy' => [['price' => '96000.00'], []],
    'the symbols differ' => [['symbol' => Symbol::Eth], []],
    'the amounts differ' => [['amount' => '0.02000000'], []],
    'the sell is already filled' => [['status' => OrderStatus::Filled], []],
    'the sell is cancelled' => [['status' => OrderStatus::Cancelled], []],
]);

test('an order does not match the same user own counter order', function () {
    $user = User::factory()->funded('100000.00')->create();

    Asset::factory()->locked('0.01000000')->create(['user_id' => $user->id]);

    $sellOrder = Order::factory()->sell()->create([
        'user_id' => $user->id,
        'price' => '94000.00',
        'amount' => '0.01000000',
    ]);

    $this->actingAs($user)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'no-self-match'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');

    expect($sellOrder->refresh()->status)->toBe(OrderStatus::Open)
        ->and(Trade::query()->count())->toBe(0);
});

test('a resting order is only matched once', function () {
    [, $sellOrder] = restingSell('94000.00');

    $firstBuyer = User::factory()->funded('100000.00')->create();
    $secondBuyer = User::factory()->funded('100000.00')->create();

    $payload = [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ];

    $this->actingAs($firstBuyer)->postJson('/api/orders', $payload, ['Idempotency-Key' => 'first'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'filled');

    $this->actingAs($secondBuyer)->postJson('/api/orders', $payload, ['Idempotency-Key' => 'second'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');

    expect($sellOrder->refresh()->status)->toBe(OrderStatus::Filled)
        ->and(Trade::query()->count())->toBe(1)
        ->and($secondBuyer->refresh()->locked_balance)->toBe('964.25');
});

test('replaying an idempotent request does not match a second time', function () {
    [, $sellOrder] = restingSell('94000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $payload = [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ];

    $first = $this->actingAs($buyer)->postJson('/api/orders', $payload, ['Idempotency-Key' => 'replay']);
    $second = $this->actingAs($buyer)->postJson('/api/orders', $payload, ['Idempotency-Key' => 'replay']);

    $first->assertCreated();
    $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));

    expect(Trade::query()->count())->toBe(1)
        ->and(Order::query()->count())->toBe(2)
        ->and($sellOrder->refresh()->status)->toBe(OrderStatus::Filled)
        ->and($buyer->refresh()->balance)->toBe('99045.90');
});

test('after a trade the combined usd balances fall by exactly the buyer fee', function () {
    $seller = User::factory()->funded('100000.00')->create();

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => Symbol::Btc,
        'amount' => '1.00000000',
    ]);

    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($seller)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'sell',
        'price' => '90000.00',
        'amount' => '0.50000000',
    ], ['Idempotency-Key' => 'reconcile-sell'])->assertCreated();

    $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.50000000',
    ], ['Idempotency-Key' => 'reconcile-buy'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'filled');

    // 90,000.00 x 0.5 = 45,000.00 gross, and 1.5% = 675.00 paid by the buyer.
    $trade = Trade::query()->sole();

    expect($trade->price)->toBe('90000.00')
        ->and($trade->gross_amount)->toBe('45000.00')
        ->and($trade->fee)->toBe('675.00');

    $seller->refresh();
    $buyer->refresh();

    expect($seller->balance)->toBe('145000.00')
        ->and($buyer->balance)->toBe('54325.00')
        ->and($buyer->locked_balance)->toBe('0.00');

    // The only USD leaving the system is the buyer's fee.
    $combined = BigDecimal::of($seller->balance)->plus($buyer->balance);

    expect((string) BigDecimal::of('200000.00')->minus($combined))->toBe('675.00')
        ->and($seller->assets()->sole()->amount)->toBe('0.50000000')
        ->and($buyer->assets()->sole()->amount)->toBe('0.50000000');
});

test('a filled order can no longer be cancelled', function () {
    restingSell('94000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $orderId = $this->actingAs($buyer)->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ], ['Idempotency-Key' => 'no-cancel-after-fill'])->json('data.id');

    $this->actingAs($buyer)->postJson("/api/orders/{$orderId}/cancel")->assertStatus(409);

    expect($buyer->refresh()->balance)->toBe('99045.90');
});
