<?php

declare(strict_types=1);

use App\Enums\Symbol;
use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * @return array{0: User, 1: Order}
 */
function sellerWithLockedBtc(string $price, bool $withAsset = true): array
{
    $seller = User::factory()->funded('0.00')->create();

    if ($withAsset) {
        Asset::factory()->locked('0.01000000')->create([
            'user_id' => $seller->id,
            'symbol' => Symbol::Btc,
            'amount' => '0.00000000',
        ]);
    }

    $order = Order::factory()->sell()->forSymbol(Symbol::Btc)->create([
        'user_id' => $seller->id,
        'price' => $price,
        'amount' => '0.01000000',
    ]);

    return [$seller, $order];
}

/**
 * @return array{symbol: string, side: string, price: string, amount: string}
 */
function buyBtcPayload(): array
{
    return [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => '95000.00',
        'amount' => '0.01000000',
    ];
}

beforeEach(function () {
    Event::fake([OrderMatched::class]);
});

test('a match broadcasts OrderMatched to the buyer and seller private channels', function () {
    [$seller, $sellOrder] = sellerWithLockedBtc('94000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $response = $this->actingAs($buyer)
        ->postJson('/api/orders', buyBtcPayload(), ['Idempotency-Key' => 'broadcast-match'])
        ->assertCreated();

    $trade = Trade::query()->sole();

    Event::assertDispatchedTimes(OrderMatched::class, 1);
    Event::assertDispatched(OrderMatched::class, function (OrderMatched $event) use ($buyer, $seller, $sellOrder, $response, $trade): bool {
        $channels = array_map(fn ($channel): string => $channel->name, $event->broadcastOn());

        return $channels === ["private-user.{$buyer->id}", "private-user.{$seller->id}"]
            && $event->broadcastAs() === 'OrderMatched'
            && $event->broadcastWith() === [
                'trade_id' => $trade->id,
                'symbol' => 'BTC',
                'orders' => [
                    ['id' => $response->json('data.id'), 'status' => 'filled'],
                    ['id' => $sellOrder->id, 'status' => 'filled'],
                ],
            ];
    });
});

test('an order without a match does not broadcast', function () {
    sellerWithLockedBtc('100000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($buyer)
        ->postJson('/api/orders', buyBtcPayload(), ['Idempotency-Key' => 'broadcast-no-match'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');

    Event::assertNotDispatched(OrderMatched::class);
});

test('an idempotent replay does not broadcast the match again', function () {
    sellerWithLockedBtc('94000.00');
    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($buyer)
        ->postJson('/api/orders', buyBtcPayload(), ['Idempotency-Key' => 'broadcast-replay'])
        ->assertCreated();

    $this->actingAs($buyer)
        ->postJson('/api/orders', buyBtcPayload(), ['Idempotency-Key' => 'broadcast-replay'])
        ->assertCreated();

    Event::assertDispatchedTimes(OrderMatched::class, 1);
});

test('a rolled back match does not broadcast', function () {
    sellerWithLockedBtc('94000.00', withAsset: false);
    $buyer = User::factory()->funded('100000.00')->create();

    $this->actingAs($buyer)
        ->postJson('/api/orders', buyBtcPayload(), ['Idempotency-Key' => 'broadcast-rollback'])
        ->assertConflict();

    expect(Trade::query()->count())->toBe(0);

    Event::assertNotDispatched(OrderMatched::class);
});
