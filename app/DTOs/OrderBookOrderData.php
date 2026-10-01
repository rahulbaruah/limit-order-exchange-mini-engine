<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\OrderSide;

final readonly class OrderBookOrderData
{
    public function __construct(
        public int $id,
        public OrderSide $side,
        public string $price,
        public string $amount,
    ) {}
}
