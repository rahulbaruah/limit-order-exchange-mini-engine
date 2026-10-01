<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\CalculatesOrderAmounts;
use App\DTOs\OrderMatchedData;
use App\DTOs\RecordTradeData;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderMatched;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Repositories\AssetRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TradeRepository;
use App\Repositories\UserRepository;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderMatchingService
{
    use CalculatesOrderAmounts;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly AssetRepository $assetRepository,
        private readonly TradeRepository $tradeRepository,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * Match a freshly placed order against a counter-order locked by the caller.
     *
     * Only full matches are executed. When no counter-order qualifies the order
     * is left open and null is returned. The caller must hold the transaction,
     * participant user locks, and the candidate order lock.
     *
     * @param  Collection<int, User>  $lockedUsers
     *
     * @throws HttpResponseException When the seller's reserved asset balance is missing.
     */
    public function match(Order $order, ?Order $counterOrder, Collection $lockedUsers): ?Trade
    {
        if ($counterOrder === null || $counterOrder->status !== OrderStatus::Open) {
            return null;
        }

        [$buyOrder, $sellOrder] = $order->side === OrderSide::Buy
            ? [$order, $counterOrder]
            : [$counterOrder, $order];

        $buyer = $lockedUsers->get((int) $buyOrder->user_id);
        $seller = $lockedUsers->get((int) $sellOrder->user_id);

        if (! $buyer instanceof User || ! $seller instanceof User) {
            throw new \LogicException('Both trade participants must be locked before settlement.');
        }

        return $this->settle($buyOrder, $sellOrder, $counterOrder->price, $buyer, $seller);
    }

    /**
     * Execute the trade at the resting order's price and fill both sides.
     *
     * @throws HttpResponseException When the seller's reserved asset balance is missing.
     */
    private function settle(Order $buyOrder, Order $sellOrder, string $tradePrice, User $buyer, User $seller): Trade
    {
        $amount = $buyOrder->amount;

        $gross = $this->notionalFor($tradePrice, $amount);
        $fee = $this->feeFor($tradePrice, $amount);

        $this->settleBuyer($buyOrder, $gross, $fee, $buyer);
        $this->settleSeller($sellOrder, $gross, $seller);

        $this->orderRepository->updateStatus($buyOrder, OrderStatus::Filled);
        $this->orderRepository->updateStatus($sellOrder, OrderStatus::Filled);

        $trade = $this->tradeRepository->create(new RecordTradeData(
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

        OrderMatched::dispatch(OrderMatchedData::fromTrade($trade));

        return $trade;
    }

    /**
     * Release the buyer's reservation, charge the trade's cost and fee, and credit the asset.
     *
     * The buyer reserved against their own limit price, so trading at a better
     * resting price refunds both the unspent notional and the fee overcharge.
     */
    private function settleBuyer(Order $buyOrder, BigDecimal $gross, BigDecimal $fee, User $buyer): void
    {
        $reserved = $this->notionalFor($buyOrder->price, $buyOrder->amount);
        $reservedFee = $this->feeFor($buyOrder->price, $buyOrder->amount);
        $lockedReservation = $reserved->plus($reservedFee);
        $refund = $reserved->minus($gross)->plus($reservedFee->minus($fee));

        $this->userRepository->updateBalances(
            $buyer,
            BigDecimal::of($buyer->balance)->plus($refund)->toScale(self::UsdScale)->__toString(),
            BigDecimal::of($buyer->locked_balance)->minus($lockedReservation)->toScale(self::UsdScale)->__toString(),
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
    private function settleSeller(Order $sellOrder, BigDecimal $gross, User $seller): void
    {
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
