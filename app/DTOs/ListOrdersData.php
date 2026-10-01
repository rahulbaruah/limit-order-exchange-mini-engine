<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use App\Http\Requests\ListOrdersRequest;

final readonly class ListOrdersData
{
    public function __construct(
        public Symbol $symbol,
        public ?OrderSide $side,
        public ?OrderStatus $status,
    ) {}

    /**
     * Build the DTO from validated request input.
     */
    public static function fromRequest(ListOrdersRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            symbol: Symbol::from((string) $validated['symbol']),
            side: isset($validated['side']) ? OrderSide::from((string) $validated['side']) : null,
            status: isset($validated['status']) ? OrderStatus::from((string) $validated['status']) : null,
        );
    }
}
