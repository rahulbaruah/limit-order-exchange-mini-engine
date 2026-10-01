<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\DTOs\GetOrderBookData;
use App\DTOs\OrderBookData;
use App\DTOs\OrderBookOrderData;
use App\Repositories\OrderRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class BuildOrderBook
{
    public function __construct(private OrderRepository $orderRepository) {}

    public function handle(GetOrderBookData $data): OrderBookData
    {
        /** @var list<OrderBookOrderData> $askOrders */
        $askOrders = [];
        /** @var list<OrderBookOrderData> $bidOrders */
        $bidOrders = [];

        foreach ($this->orderRepository->openOrdersForBook($data->symbol) as $order) {
            if ($order->side->value === 'sell') {
                $askOrders[] = $order;

                continue;
            }

            $bidOrders[] = $order;
        }

        usort($askOrders, self::compareAsks(...));
        usort($bidOrders, self::compareBids(...));

        $bestAsk = $askOrders[0]->price ?? null;
        $bestBid = $bidOrders[0]->price ?? null;
        $spread = $bestAsk !== null && $bestBid !== null
            ? (string) BigDecimal::of($bestAsk)->minus($bestBid)->toScale(2, RoundingMode::Unnecessary)
            : null;

        $bestAsks = array_reverse(array_slice($askOrders, 0, 4));
        $bestBids = array_slice($bidOrders, 0, 4);

        return new OrderBookData(
            asks: $this->formatOrders($bestAsks),
            bids: $this->formatOrders($bestBids),
            spread: $spread,
        );
    }

    /**
     * @param  list<OrderBookOrderData>  $orders
     * @return list<array{id: int, price: string, amount: string}>
     */
    private function formatOrders(array $orders): array
    {
        return array_map(
            static fn (OrderBookOrderData $order): array => [
                'id' => $order->id,
                'price' => $order->price,
                'amount' => $order->amount,
            ],
            $orders,
        );
    }

    private static function compareAsks(OrderBookOrderData $left, OrderBookOrderData $right): int
    {
        $priceComparison = BigDecimal::of($left->price)->compareTo(BigDecimal::of($right->price));

        return $priceComparison !== 0 ? $priceComparison : $left->id <=> $right->id;
    }

    private static function compareBids(OrderBookOrderData $left, OrderBookOrderData $right): int
    {
        $priceComparison = BigDecimal::of($right->price)->compareTo(BigDecimal::of($left->price));

        return $priceComparison !== 0 ? $priceComparison : $left->id <=> $right->id;
    }
}
