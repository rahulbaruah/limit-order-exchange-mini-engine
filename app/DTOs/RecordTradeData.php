<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Symbol;

final readonly class RecordTradeData
{
    public function __construct(
        public int $buyOrderId,
        public int $sellOrderId,
        public int $buyerId,
        public int $sellerId,
        public Symbol $symbol,
        public string $price,
        public string $amount,
        public string $grossAmount,
        public string $fee,
    ) {}

    /**
     * Map the DTO to trade model attributes.
     *
     * @return array{buy_order_id: int, sell_order_id: int, buyer_id: int, seller_id: int, symbol: string, price: string, amount: string, gross_amount: string, fee: string}
     */
    public function toArray(): array
    {
        return [
            'buy_order_id' => $this->buyOrderId,
            'sell_order_id' => $this->sellOrderId,
            'buyer_id' => $this->buyerId,
            'seller_id' => $this->sellerId,
            'symbol' => $this->symbol->value,
            'price' => $this->price,
            'amount' => $this->amount,
            'gross_amount' => $this->grossAmount,
            'fee' => $this->fee,
        ];
    }
}
