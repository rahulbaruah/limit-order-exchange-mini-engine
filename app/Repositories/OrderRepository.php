<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateOrderData;
use App\DTOs\OrderBookOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class OrderRepository
{
    /**
     * Create a new order from the given data.
     */
    public function create(CreateOrderData $data, OrderStatus $status): Order
    {
        return Order::query()->create([
            ...$data->toArray(),
            'status' => $status->value,
        ]);
    }

    /**
     * Find a user's order by the idempotency key it was created with.
     */
    public function findByIdempotencyKey(int $userId, string $idempotencyKey): ?Order
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Retrieve an order owned by the user with a write lock for the duration of the transaction.
     */
    public function findOwnedForUpdate(int $orderId, int $userId): ?Order
    {
        return Order::query()
            ->whereKey($orderId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Find the best counter-order candidate without acquiring a row lock.
     */
    public function findMatchableCounterOrderCandidate(CreateOrderData $data): ?Order
    {
        return $this->matchableCounterOrders($data)->first();
    }

    /**
     * Lock the previously discovered candidate only if it is still matchable.
     */
    public function lockMatchableCounterOrderForUpdate(CreateOrderData $data, int $candidateId): ?Order
    {
        return $this->matchableCounterOrders($data)
            ->whereKey($candidateId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return Builder<Order>
     */
    private function matchableCounterOrders(CreateOrderData $data): Builder
    {
        $isBuy = $data->side === OrderSide::Buy;

        return Order::query()
            ->where('symbol', $data->symbol->value)
            ->where('side', $isBuy ? OrderSide::Sell->value : OrderSide::Buy->value)
            ->where('status', OrderStatus::Open->value)
            ->where('user_id', '!=', $data->userId)
            ->where('amount', $data->amount)
            ->where('price', $isBuy ? '<=' : '>=', $data->price)
            ->orderBy('price', $isBuy ? 'asc' : 'desc')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Retrieve every order the user placed for a symbol.
     *
     * Includes the user's own buy and sell orders across every order status.
     * Other users' orders, including sells matched against the user's buys,
     * are excluded.
     *
     * @return Collection<int, Order>
     */
    public function visibleForUser(int $userId, Symbol $symbol): Collection
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('symbol', $symbol->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Retrieve open orders for a market-book snapshot, without exposing owner data.
     *
     * @return SupportCollection<int, OrderBookOrderData>
     */
    public function openOrdersForBook(Symbol $symbol): SupportCollection
    {
        return collect(Order::query()
            ->where('symbol', $symbol->value)
            ->where('status', OrderStatus::Open->value)
            ->get(['id', 'side', 'price', 'amount'])
            ->map(static fn (Order $order): OrderBookOrderData => new OrderBookOrderData(
                $order->id,
                $order->side,
                $order->price,
                $order->amount,
            ))
            ->all());
    }

    /**
     * Persist the order's status.
     */
    public function updateStatus(Order $order, OrderStatus $status): void
    {
        $order->forceFill([
            'status' => $status->value,
        ])->save();
    }
}
