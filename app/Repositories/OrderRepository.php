<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

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
     * Find the best open counter-order that fully matches the given order, locked for the transaction.
     *
     * Only exact-amount matches on the opposite side qualify, and a user never
     * trades with themselves. Candidates are ranked by price-time priority: the
     * cheapest sell (or the highest-paying buy), then the earliest placed.
     */
    public function findMatchableCounterOrderForUpdate(Order $order): ?Order
    {
        $isBuy = $order->side === OrderSide::Buy;

        return Order::query()
            ->where('symbol', $order->symbol->value)
            ->where('side', $isBuy ? OrderSide::Sell->value : OrderSide::Buy->value)
            ->where('status', OrderStatus::Open->value)
            ->whereKeyNot($order->id)
            ->where('user_id', '!=', $order->user_id)
            ->where('amount', $order->amount)
            ->where('price', $isBuy ? '<=' : '>=', $order->price)
            ->orderBy('price', $isBuy ? 'asc' : 'desc')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
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
     * Persist the order's status.
     */
    public function updateStatus(Order $order, OrderStatus $status): void
    {
        $order->forceFill([
            'status' => $status->value,
        ])->save();
    }
}
