<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Http\Request;

final readonly class CancelOrderData
{
    public function __construct(
        public int $userId,
        public int $orderId,
    ) {}

    /**
     * Build the DTO from the authenticated request and route binding.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            userId: (int) $request->user()->id,
            orderId: (int) $request->route('order'),
        );
    }
}
