<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
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
     * Retrieve the orders a user is involved in for a symbol.
     *
     * Includes the user's own buy orders and the sell orders that were matched
     * against those buys, across every order status. Other users' unrelated
     * orders are excluded.
     *
     * @return Collection<int, Order>
     */
    public function visibleForUser(int $userId, Symbol $symbol): Collection
    {
        return Order::query()
            ->where('symbol', $symbol->value)
            ->where(function (Builder $query) use ($userId): void {
                $query
                    ->where(function (Builder $query) use ($userId): void {
                        $query->where('user_id', $userId)
                            ->where('side', OrderSide::Buy->value);
                    })
                    ->orWhere(function (Builder $query) use ($userId): void {
                        $query->where('side', OrderSide::Sell->value)
                            ->whereHas('sellTrades', fn (Builder $trade): Builder => $trade->where('buyer_id', $userId));
                    });
            })
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
