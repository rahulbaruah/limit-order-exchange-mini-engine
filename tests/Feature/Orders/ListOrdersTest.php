<?php

declare(strict_types=1);

use App\Enums\Symbol;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/orders?symbol=BTC')->assertUnauthorized();
});

test('a missing symbol is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/orders')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('symbol');
});

test('an unsupported symbol is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/orders?symbol=DOGE')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('symbol');
});

test('the authenticated users own buy orders are returned across every status', function () {
    $me = User::factory()->create();

    $open = Order::factory()->for($me)->buy()->create();
    $filled = Order::factory()->for($me)->buy()->filled()->create();
    $cancelled = Order::factory()->for($me)->buy()->cancelled()->create();

    $response = $this->actingAs($me)->getJson('/api/orders?symbol=BTC')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    expect($response->json('data.*.id'))
        ->toContain($open->id)
        ->toContain($filled->id)
        ->toContain($cancelled->id);
});

test('the authenticated users own sell orders are returned across every status', function () {
    $me = User::factory()->create();

    $open = Order::factory()->for($me)->sell()->create();
    $filled = Order::factory()->for($me)->sell()->filled()->create();
    $cancelled = Order::factory()->for($me)->sell()->cancelled()->create();

    $response = $this->actingAs($me)->getJson('/api/orders?symbol=BTC')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    expect($response->json('data.*.id'))
        ->toContain($open->id)
        ->toContain($filled->id)
        ->toContain($cancelled->id);
});

test('a matched counterparty sell order is not returned', function () {
    $me = User::factory()->create();
    $seller = User::factory()->create();

    $buy = Order::factory()->for($me)->buy()->create();
    $sell = Order::factory()->for($seller)->sell()->create();

    Trade::factory()->create([
        'buy_order_id' => $buy->id,
        'sell_order_id' => $sell->id,
        'buyer_id' => $me->id,
        'seller_id' => $seller->id,
    ]);

    $response = $this->actingAs($me)->getJson('/api/orders?symbol=BTC')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.*.id'))->toBe([$buy->id]);
});

test('orders owned by other users are excluded', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $myBuy = Order::factory()->for($me)->buy()->create();
    $mySell = Order::factory()->for($me)->sell()->create();

    Order::factory()->for($other)->buy()->create();
    Order::factory()->for($other)->sell()->create();

    $response = $this->actingAs($me)->getJson('/api/orders?symbol=BTC')->assertOk();

    expect($response->json('data.*.id'))
        ->toHaveCount(2)
        ->toContain($myBuy->id)
        ->toContain($mySell->id);
});

test('orders for other symbols are excluded', function () {
    $me = User::factory()->create();

    Order::factory()->for($me)->buy()->create();
    Order::factory()->for($me)->buy()->forSymbol(Symbol::Eth)->create();

    $this->actingAs($me)->getJson('/api/orders?symbol=BTC')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('the most recently created orders are returned first', function () {
    $me = User::factory()->create();

    $first = Order::factory()->for($me)->buy()->create();
    $second = Order::factory()->for($me)->buy()->create();

    $response = $this->actingAs($me)->getJson('/api/orders?symbol=BTC')->assertOk();

    expect($response->json('data.*.id'))->toBe([$second->id, $first->id]);
});

test('the response does not expose the owning user', function () {
    $me = User::factory()->create();
    Order::factory()->for($me)->buy()->create();

    $response = $this->actingAs($me)->getJson('/api/orders?symbol=BTC')->assertOk();

    expect(array_keys($response->json('data.0')))->not->toContain('user_id');
});

test('an empty list is returned when the user has no relevant orders', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Order::factory()->for($other)->buy()->create();
    Order::factory()->for($me)->buy()->forSymbol(Symbol::Eth)->create();

    $this->actingAs($me)->getJson('/api/orders?symbol=BTC')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the order creation timestamp is exposed as an ISO-8601 string', function () {
    $me = User::factory()->create();
    $order = Order::factory()->for($me)->buy()->create();

    $response = $this->actingAs($me)->getJson('/api/orders?symbol=BTC')->assertOk();

    expect($response->json('data.0.created_at'))
        ->toBe($order->created_at?->toIso8601String());
});
