<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Symbol;
use App\Models\Trade;

final readonly class OrderMatchedData
{
    public function __construct(
        public int $tradeId,
        public int $buyOrderId,
        public int $sellOrderId,
        public int $buyerId,
        public int $sellerId,
        public Symbol $symbol,
    ) {}

    public static function fromTrade(Trade $trade): self
    {
        return new self(
            tradeId: (int) $trade->id,
            buyOrderId: (int) $trade->buy_order_id,
            sellOrderId: (int) $trade->sell_order_id,
            buyerId: (int) $trade->buyer_id,
            sellerId: (int) $trade->seller_id,
            symbol: $trade->symbol,
        );
    }
}
