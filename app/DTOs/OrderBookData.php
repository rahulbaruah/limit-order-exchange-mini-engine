<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class OrderBookData
{
    /**
     * @param  list<array{id: int, price: string, amount: string}>  $asks
     * @param  list<array{id: int, price: string, amount: string}>  $bids
     */
    public function __construct(
        public array $asks,
        public array $bids,
        public ?string $spread,
    ) {}

    /**
     * @return array{
     *     asks: list<array{id: int, price: string, amount: string}>,
     *     bids: list<array{id: int, price: string, amount: string}>,
     *     spread: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'asks' => $this->asks,
            'bids' => $this->bids,
            'spread' => $this->spread,
        ];
    }
}
