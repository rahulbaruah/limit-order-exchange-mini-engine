<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Concerns\CalculatesOrderAmounts;
use App\DTOs\CancelOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Repositories\AssetRepository;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use Brick\Math\BigDecimal;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class CancelOrder
{
    use CalculatesOrderAmounts;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly AssetRepository $assetRepository,
    ) {}

    /**
     * Cancel an open order owned by the user and release the funds or assets it reserves.
     *
     * A cancelled buy returns the locked notional plus the upfront fee to the
     * USD balance; a cancelled sell moves the locked asset amount back into the
     * available asset balance.
     *
     * @throws HttpResponseException When the order is missing, not owned, or not open.
     */
    public function handle(CancelOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $user = $this->userRepository->lockById($data->userId);

            $order = $this->orderRepository->findOwnedForUpdate($data->orderId, $data->userId);

            if ($order === null) {
                abort(404, __('Order not found.'));
            }

            if ($order->status !== OrderStatus::Open) {
                abort(409, __('Only open orders can be cancelled.'));
            }

            match ($order->side) {
                OrderSide::Buy => $this->releaseBuyReservation($user, $order),
                OrderSide::Sell => $this->releaseSellReservation($data->userId, $order),
            };

            $this->orderRepository->updateStatus($order, OrderStatus::Cancelled);

            return $order;
        });
    }

    /**
     * Return a cancelled buy's locked notional and upfront fee to the USD balance.
     */
    private function releaseBuyReservation(User $user, Order $order): void
    {
        $notional = $this->notionalFor($order->price, $order->amount);
        $fee = $this->feeFor($order->price, $order->amount);

        $this->userRepository->updateBalances(
            $user,
            BigDecimal::of($user->balance)
                ->plus($notional)
                ->plus($fee)
                ->toScale(self::UsdScale)
                ->__toString(),
            BigDecimal::of($user->locked_balance)
                ->minus($notional)
                ->toScale(self::UsdScale)
                ->__toString(),
        );
    }

    /**
     * Move a cancelled sell's locked asset amount back into the available balance.
     */
    private function releaseSellReservation(int $userId, Order $order): void
    {
        $asset = $this->assetRepository->findForUpdate($userId, $order->symbol);

        if ($asset === null) {
            abort(409, __('The reserved asset balance for this order is unavailable.'));
        }

        $amount = BigDecimal::of($order->amount);

        $this->assetRepository->updateBalances(
            $asset,
            BigDecimal::of($asset->amount)->plus($amount)->toScale(self::AssetScale)->__toString(),
            BigDecimal::of($asset->locked_amount)->minus($amount)->toScale(self::AssetScale)->__toString(),
        );
    }
}
