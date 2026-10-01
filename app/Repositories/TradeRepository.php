<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\RecordTradeData;
use App\Models\Trade;

class TradeRepository
{
    /**
     * Record an executed trade.
     */
    public function create(RecordTradeData $data): Trade
    {
        return Trade::query()->create($data->toArray());
    }
}
