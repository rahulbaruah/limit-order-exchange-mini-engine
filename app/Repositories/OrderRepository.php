<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateOrderData;
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
