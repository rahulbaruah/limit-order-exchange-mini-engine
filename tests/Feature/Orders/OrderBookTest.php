<?php

declare(strict_types=1);

use App\Enums\Symbol;
use App\Models\Order;
use App\Models\User;

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/order-book?symbol=BTC')->assertUnauthorized();
});

test('a missing symbol is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/order-book')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('symbol');
});

test('an unsupported symbol is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/order-book?symbol=DOGE')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('symbol');
});

test('open orders are listed individually in best-price order', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Order::factory()->for($other)->sell()->create(['price' => '101.00', 'amount' => '0.10000000']);
    Order::factory()->for($user)->sell()->create(['price' => '101.00', 'amount' => '0.20000000']);
    Order::factory()->for($other)->sell()->create(['price' => '102.00', 'amount' => '0.10000000']);
    Order::factory()->for($other)->sell()->create(['price' => '103.00', 'amount' => '0.10000000']);
    Order::factory()->for($other)->sell()->create(['price' => '104.00', 'amount' => '0.10000000']);
    Order::factory()->for($other)->sell()->create(['price' => '105.00', 'amount' => '0.10000000']);
    Order::factory()->for($other)->buy()->create(['price' => '100.00']);
    Order::factory()->for($other)->buy()->create(['price' => '99.00']);
    Order::factory()->for($other)->buy()->create(['price' => '98.00']);
    Order::factory()->for($other)->buy()->create(['price' => '97.00']);
    Order::factory()->for($other)->buy()->create(['price' => '96.00']);
    Order::factory()->for($other)->sell()->filled()->create(['price' => '90.00']);
    Order::factory()->for($other)->buy()->cancelled()->create(['price' => '110.00']);
    Order::factory()->for($other)->sell()->forSymbol(Symbol::Eth)->create(['price' => '80.00']);

    $response = $this->actingAs($user)->getJson('/api/order-book?symbol=BTC')
        ->assertOk()
        ->assertJsonPath('data.asks.0.price', '103.00')
        ->assertJsonPath('data.asks.3.price', '101.00')
        ->assertJsonPath('data.asks.2.amount', '0.20000000')
        ->assertJsonPath('data.asks.3.amount', '0.10000000')
        ->assertJsonPath('data.bids.0.price', '100.00')
        ->assertJsonPath('data.bids.3.price', '97.00')
        ->assertJsonPath('data.spread', '1.00');

    $askRows = $response->json('data.asks');

    expect($askRows)->toHaveCount(4)
        ->and(array_column($askRows, 'price'))->toBe(['103.00', '102.00', '101.00', '101.00'])
        ->and(array_unique(array_column($askRows, 'id')))->toHaveCount(4)
        ->and(array_keys($askRows[0]))->toBe(['id', 'price', 'amount'])
        ->and($response->json('data.bids'))->toHaveCount(4);
});

test('an empty or one-sided book has no spread', function () {
    $user = User::factory()->create();

    $emptyResponse = $this->actingAs($user)->getJson('/api/order-book?symbol=BTC')
        ->assertOk()
        ->assertJsonPath('data.asks', [])
        ->assertJsonPath('data.bids', [])
        ->assertJsonPath('data.spread', null);

    Order::factory()->for($user)->buy()->create(['price' => '95.00']);

    $oneSidedResponse = $this->actingAs($user)->getJson('/api/order-book?symbol=BTC')
        ->assertOk()
        ->assertJsonPath('data.asks', [])
        ->assertJsonPath('data.bids.0.price', '95.00')
        ->assertJsonPath('data.spread', null);

    expect($emptyResponse->json('data.bids'))->toBe([])
        ->and($oneSidedResponse->json('data.bids'))->toHaveCount(1);
});

test('the ETH book excludes BTC orders', function () {
    $user = User::factory()->create();

    Order::factory()->for($user)->buy()->create(['price' => '100.00']);
    Order::factory()->for($user)->sell()->forSymbol(Symbol::Eth)->create([
        'price' => '200.00',
        'amount' => '0.50000000',
    ]);

    $this->actingAs($user)->getJson('/api/order-book?symbol=ETH')
        ->assertOk()
        ->assertJsonPath('data.asks.0.price', '200.00')
        ->assertJsonPath('data.asks.0.amount', '0.50000000')
        ->assertJsonPath('data.bids', [])
        ->assertJsonPath('data.spread', null);
});
