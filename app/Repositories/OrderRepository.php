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
     * Retrieve every open order for a symbol, ordered as a market book.
     *
     * Buys are returned before sells; buys are ordered by descending price and
     * sells by ascending price, with the oldest order first to break ties.
     *
     * @return Collection<int, Order>
     */
    public function openForSymbol(Symbol $symbol): Collection
    {
        return Order::query()
            ->where('symbol', $symbol->value)
            ->where('status', OrderStatus::Open->value)
            ->orderByRaw('CASE WHEN side = ? THEN 0 ELSE 1 END', [OrderSide::Buy->value])
            ->orderByRaw('CASE WHEN side = ? THEN price END DESC', [OrderSide::Buy->value])
            ->orderByRaw('CASE WHEN side = ? THEN price END ASC', [OrderSide::Sell->value])
            ->orderBy('created_at')
            ->orderBy('id')
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
