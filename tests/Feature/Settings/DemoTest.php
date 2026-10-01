<?php

declare(strict_types=1);

use App\Models\Asset;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('demo balances page is displayed', function () {
    $user = User::factory()->create(['balance' => '1234.50', 'locked_balance' => '950.00']);

    Asset::factory()->for($user)->create([
        'symbol' => 'BTC',
        'amount' => '0.50000000',
        'locked_amount' => '0.01000000',
    ]);

    $this->actingAs($user)
        ->get(route('demo.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Demo')
            ->where('balances.usd.balance', '1234.50')
            ->where('balances.usd.locked_balance', '950.00')
            ->where('balances.assets.0.symbol', 'BTC')
            ->where('balances.assets.0.amount', '0.50000000')
            ->where('symbols', ['BTC', 'ETH']),
        );
});

test('balances and assets can be overwritten', function () {
    $user = User::factory()->create();

    Asset::factory()->for($user)->create(['symbol' => 'BTC', 'amount' => '0.50000000']);

    $this->actingAs($user)
        ->put(route('demo.update'), [
            'balance' => '1000.50',
            'locked_balance' => '25.00',
            'assets' => [
                ['symbol' => 'BTC', 'amount' => '2.00000000', 'locked_amount' => '0.50000000'],
                ['symbol' => 'ETH', 'amount' => '10.00000000', 'locked_amount' => '1.00000000'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('demo.edit'));

    $user->refresh();

    $btc = Asset::query()->where('user_id', $user->id)->where('symbol', 'BTC')->sole();
    $eth = Asset::query()->where('user_id', $user->id)->where('symbol', 'ETH')->sole();

    expect($user->balance)->toBe('1000.50')
        ->and($user->locked_balance)->toBe('25.00')
        ->and($btc->amount)->toBe('2.00000000')
        ->and($btc->locked_amount)->toBe('0.50000000')
        ->and($eth->amount)->toBe('10.00000000')
        ->and($eth->locked_amount)->toBe('1.00000000');
});

test('asset rows are created when the user has none', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('demo.update'), [
            'balance' => '0.00',
            'locked_balance' => '0.00',
            'assets' => [
                ['symbol' => 'BTC', 'amount' => '1.25000000', 'locked_amount' => '0.00000000'],
            ],
        ])
        ->assertSessionHasNoErrors();

    $asset = Asset::query()->where('user_id', $user->id)->where('symbol', 'BTC')->sole();

    expect($asset->amount)->toBe('1.25000000')
        ->and($asset->locked_amount)->toBe('0.00000000');
});

test('negative balances are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('demo.edit'))
        ->put(route('demo.update'), [
            'balance' => '-1.00',
            'locked_balance' => '0.00',
            'assets' => [
                ['symbol' => 'BTC', 'amount' => '-1.00000000', 'locked_amount' => '0.00000000'],
            ],
        ])
        ->assertSessionHasErrors(['balance', 'assets.0.amount'])
        ->assertRedirect(route('demo.edit'));
});

test('unsupported asset symbols are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('demo.edit'))
        ->put(route('demo.update'), [
            'balance' => '0.00',
            'locked_balance' => '0.00',
            'assets' => [
                ['symbol' => 'DOGE', 'amount' => '1.00000000', 'locked_amount' => '0.00000000'],
            ],
        ])
        ->assertSessionHasErrors('assets.0.symbol')
        ->assertRedirect(route('demo.edit'));
});

test('guests cannot view the demo balances page', function () {
    $this->get(route('demo.edit'))->assertRedirect(route('login'));
});
