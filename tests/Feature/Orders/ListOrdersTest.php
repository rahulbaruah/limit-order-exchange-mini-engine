<?php

declare(strict_types=1);

use App\Enums\Symbol;
use App\Models\Order;
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

test('open orders for the requested symbol from every user are returned', function () {
    $caller = User::factory()->create();
    $other = User::factory()->create();

    Order::factory()->for($caller)->create();
    Order::factory()->for($other)->sell()->create();

    $response = $this->actingAs($caller)->getJson('/api/orders?symbol=BTC')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(array_keys($response->json('data.0')))->not->toContain('user_id');
});

test('orders for other symbols and non open orders are excluded', function () {
    $user = User::factory()->create();

    Order::factory()->for($user)->create();
    Order::factory()->for($user)->forSymbol(Symbol::Eth)->create();
    Order::factory()->for($user)->filled()->create();
    Order::factory()->for($user)->cancelled()->create();

    $this->actingAs($user)->getJson('/api/orders?symbol=BTC')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'open');
});

test('the book lists buys by highest price then sells by lowest price', function () {
    $user = User::factory()->create();

    Order::factory()->for($user)->sell()->create(['price' => '97000.00']);
    Order::factory()->for($user)->buy()->create(['price' => '94000.00']);
    Order::factory()->for($user)->sell()->create(['price' => '95000.00']);
    Order::factory()->for($user)->buy()->create(['price' => '96000.00']);

    $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC')->assertOk();

    expect($response->json('data.*.side'))->toBe(['buy', 'buy', 'sell', 'sell'])
        ->and($response->json('data.*.price'))->toBe(['96000.00', '94000.00', '95000.00', '97000.00']);
});

test('orders at the same price are returned oldest first', function () {
    $user = User::factory()->create();

    $first = Order::factory()->for($user)->buy()->create(['price' => '95000.00']);
    $second = Order::factory()->for($user)->buy()->create(['price' => '95000.00']);

    $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC')->assertOk();

    expect($response->json('data.*.id'))->toBe([$first->id, $second->id]);
});

test('an empty book is returned when no open orders match the symbol', function () {
    $user = User::factory()->create();
    Order::factory()->for($user)->forSymbol(Symbol::Eth)->create();

    $this->actingAs($user)->getJson('/api/orders?symbol=BTC')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
