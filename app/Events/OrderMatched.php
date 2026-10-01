<?php

declare(strict_types=1);

namespace App\Events;

use App\DTOs\OrderMatchedData;
use App\Enums\OrderStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly OrderMatchedData $match) {}

    /**
     * Notify the buyer and the seller on their own private channels.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->match->buyerId}"),
            new PrivateChannel("user.{$this->match->sellerId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderMatched';
    }

    /**
     * Carry only identifiers; clients refetch balances and orders from the API.
     *
     * @return array{trade_id: int, symbol: string, orders: list<array{id: int, status: string}>}
     */
    public function broadcastWith(): array
    {
        return [
            'trade_id' => $this->match->tradeId,
            'symbol' => $this->match->symbol->value,
            'orders' => [
                ['id' => $this->match->buyOrderId, 'status' => OrderStatus::Filled->value],
                ['id' => $this->match->sellOrderId, 'status' => OrderStatus::Filled->value],
            ],
        ];
    }
}
