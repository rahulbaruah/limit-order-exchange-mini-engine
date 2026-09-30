<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateOrderData;
use App\Enums\OrderStatus;
use App\Models\Order;

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
}
