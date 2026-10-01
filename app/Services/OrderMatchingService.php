<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\CalculatesOrderAmounts;
use App\DTOs\RecordTradeData;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Trade;
use App\Repositories\AssetRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TradeRepository;
use App\Repositories\UserRepository;
use Brick\Math\BigDecimal;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderMatchingService
{
    use CalculatesOrderAmounts;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly AssetRepository $assetRepository,
        private readonly TradeRepository $tradeRepository,
    ) {}

    /**
     * Match a freshly placed open order against the best resting counter-order.
     *
     * Only full matches are executed. When no counter-order qualifies the order
     * is left open and null is returned. The caller must already have opened a
     * database transaction and locked the order's owner.
     *
     * @throws HttpResponseException When the seller's reserved asset balance is missing.
     */
    public function match(Order $order): ?Trade
    {
        $counterOrder = $this->orderRepository->findMatchableCounterOrderForUpdate($order);

        // Re-check under the row lock in case the counter-order was just taken.
        if ($counterOrder === null || $counterOrder->status !== OrderStatus::Open) {
            return null;
        }

        [$buyOrder, $sellOrder] = $order->side === OrderSide::Buy
            ? [$order, $counterOrder]
            : [$counterOrder, $order];

        return $this->settle($buyOrder, $sellOrder, $counterOrder->price);
    }

    /**
     * Execute the trade at the resting order's price and fill both sides.
     *
     * @throws HttpResponseException When the seller's reserved asset balance is missing.
     */
    private function settle(Order $buyOrder, Order $sellOrder, string $tradePrice): Trade
    {
        $amount = $buyOrder->amount;

        $gross = $this->notionalFor($tradePrice, $amount);
        $fee = $this->feeFor($tradePrice, $amount);

        $this->settleBuyer($buyOrder, $gross, $fee);
        $this->settleSeller($sellOrder, $gross);

        $this->orderRepository->updateStatus($buyOrder, OrderStatus::Filled);
        $this->orderRepository->updateStatus($sellOrder, OrderStatus::Filled);

        return $this->tradeRepository->create(new RecordTradeData(
            buyOrderId: (int) $buyOrder->id,
            sellOrderId: (int) $sellOrder->id,
            buyerId: (int) $buyOrder->user_id,
            sellerId: (int) $sellOrder->user_id,
            symbol: $buyOrder->symbol,
            price: $tradePrice,
            amount: $amount,
            grossAmount: $gross->__toString(),
            fee: $fee->__toString(),
        ));
    }

    /**
     * Release the buyer's reservation, charge the trade's cost and fee, and credit the asset.
     *
     * The buyer reserved against their own limit price, so trading at a better
     * resting price refunds both the unspent notional and the fee overcharge.
     */
    private function settleBuyer(Order $buyOrder, BigDecimal $gross, BigDecimal $fee): void
    {
        $buyer = $this->userRepository->lockById((int) $buyOrder->user_id);

        $reserved = $this->notionalFor($buyOrder->price, $buyOrder->amount);
        $refund = $reserved->minus($gross)
            ->plus($this->feeFor($buyOrder->price, $buyOrder->amount)->minus($fee));

        $this->userRepository->updateBalances(
            $buyer,
            BigDecimal::of($buyer->balance)->plus($refund)->toScale(self::UsdScale)->__toString(),
            BigDecimal::of($buyer->locked_balance)->minus($reserved)->toScale(self::UsdScale)->__toString(),
        );

        $asset = $this->assetRepository->findOrCreateForUpdate((int) $buyOrder->user_id, $buyOrder->symbol);

        $this->assetRepository->updateBalances(
            $asset,
            BigDecimal::of($asset->amount)->plus($buyOrder->amount)->toScale(self::AssetScale)->__toString(),
            $asset->locked_amount,
        );
    }

    /**
     * Credit the seller with the trade proceeds and consume their locked asset.
     *
     * @throws HttpResponseException When the seller's reserved asset balance is missing.
     */
    private function settleSeller(Order $sellOrder, BigDecimal $gross): void
    {
        $seller = $this->userRepository->lockById((int) $sellOrder->user_id);

        $this->userRepository->updateBalances(
            $seller,
            BigDecimal::of($seller->balance)->plus($gross)->toScale(self::UsdScale)->__toString(),
            $seller->locked_balance,
        );

        $asset = $this->assetRepository->findForUpdate((int) $sellOrder->user_id, $sellOrder->symbol);

        if ($asset === null) {
            abort(409, __('The reserved asset balance for this order is unavailable.'));
        }

        $this->assetRepository->updateBalances(
            $asset,
            $asset->amount,
            BigDecimal::of($asset->locked_amount)
                ->minus($sellOrder->amount)
                ->toScale(self::AssetScale)
                ->__toString(),
        );
    }
}
