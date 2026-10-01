<?php

declare(strict_types=1);

use App\Enums\Symbol;
use App\Models\Asset;
use App\Models\User;

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/profile')->assertUnauthorized();
});

test('the authenticated users usd and asset balances are returned', function () {
    $user = User::factory()->create(['balance' => '1234.50', 'locked_balance' => '950.00']);

    Asset::factory()->for($user)->create([
        'symbol' => Symbol::Btc,
        'amount' => '0.50000000',
        'locked_amount' => '0.01000000',
    ]);

    Asset::factory()->for($user)->eth()->create([
        'amount' => '2.00000000',
        'locked_amount' => '0.25000000',
    ]);

    $this->actingAs($user)->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.usd.balance', '1234.50')
        ->assertJsonPath('data.usd.locked_balance', '950.00')
        ->assertJsonPath('data.assets.0.symbol', 'BTC')
        ->assertJsonPath('data.assets.0.amount', '0.50000000')
        ->assertJsonPath('data.assets.0.locked_amount', '0.01000000')
        ->assertJsonPath('data.assets.1.symbol', 'ETH')
        ->assertJsonPath('data.assets.1.amount', '2.00000000')
        ->assertJsonPath('data.assets.1.locked_amount', '0.25000000');
});

test('only the authenticated users balances are returned', function () {
    $user = User::factory()->create(['balance' => '100.00']);
    Asset::factory()->for($user)->create(['amount' => '0.50000000']);

    $other = User::factory()->create(['balance' => '999.00']);
    Asset::factory()->for($other)->create(['amount' => '7.00000000']);

    $this->actingAs($user)->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.usd.balance', '100.00')
        ->assertJsonPath('data.usd.locked_balance', '0.00')
        ->assertJsonCount(1, 'data.assets')
        ->assertJsonPath('data.assets.0.amount', '0.50000000');
});

test('zero balances are returned when the user has no funds', function () {
    $user = User::factory()->create(['balance' => '0.00', 'locked_balance' => '0.00']);

    $this->actingAs($user)->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.usd.balance', '0.00')
        ->assertJsonPath('data.usd.locked_balance', '0.00')
        ->assertJsonCount(0, 'data.assets');
});
