<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Symbol;

final readonly class DemoAssetBalanceData
{
    public function __construct(
        public Symbol $symbol,
        public string $amount,
        public string $lockedAmount,
    ) {}

    /**
     * Build the DTO from a validated asset balance entry.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            symbol: Symbol::from((string) $data['symbol']),
            amount: (string) $data['amount'],
            lockedAmount: (string) $data['locked_amount'],
        );
    }
}
