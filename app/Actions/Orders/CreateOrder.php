<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\DTOs\CreateOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Repositories\AssetRepository;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    /**
     * USD amounts are stored with two decimal places.
     */
    private const int UsdScale = 2;

    /**
     * Asset amounts are stored with eight decimal places.
     */
    private const int AssetScale = 8;

    /**
     * Fee rate charged on the order notional.
     */
    private const string FeeRate = '0.015';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly AssetRepository $assetRepository,
    ) {}

    /**
     * Create an open limit order, reserving the funds or assets it commits.
     *
     * A buy reserves the notional plus the upfront fee from the USD balance; a
     * sell moves the ordered amount from the available asset balance into the
     * asset's locked amount.
     *
     * @throws ValidationException When the available balance or assets cannot cover the order.
     */
    public function handle(CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $user = $this->userRepository->lockById($data->userId);

            $existing = $this->orderRepository->findByIdempotencyKey($data->userId, $data->idempotencyKey);

            if ($existing !== null) {
                if (! $this->matchesRequest($existing, $data)) {
                    abort(409, __('This idempotency key has already been used with different order details.'));
                }

                return $existing;
            }

            return match ($data->side) {
                OrderSide::Buy => $this->createBuyOrder($user, $data),
                OrderSide::Sell => $this->createSellOrder($data),
            };
        });
    }

    /**
     * Reserve the notional plus the upfront fee and create an open buy order.
     *
     * @throws ValidationException When the available balance cannot cover the order.
     */
    private function createBuyOrder(User $user, CreateOrderData $data): Order
    {
        $rawNotional = BigDecimal::of($data->price)->multipliedBy($data->amount);

        $notional = $rawNotional->toScale(self::UsdScale, RoundingMode::Ceiling);
        $fee = $rawNotional
            ->multipliedBy(self::FeeRate)
            ->toScale(self::UsdScale, RoundingMode::Ceiling);
        $required = $notional->plus($fee);

        $balance = BigDecimal::of($user->balance);

        if ($balance->isLessThan($required)) {
            throw ValidationException::withMessages([
                'balance' => __('Insufficient available balance to cover this order and its fee.'),
            ]);
        }

        $this->userRepository->updateBalances(
            $user,
            $balance->minus($required)->toScale(self::UsdScale)->__toString(),
            BigDecimal::of($user->locked_balance)
                ->plus($notional)
                ->toScale(self::UsdScale)
                ->__toString(),
        );

        return $this->orderRepository->create($data, OrderStatus::Open);
    }

    /**
     * Lock the ordered asset amount and create an open sell order.
     *
     * @throws ValidationException When the available asset amount cannot cover the order.
     */
    private function createSellOrder(CreateOrderData $data): Order
    {
        $asset = $this->assetRepository->findForUpdate($data->userId, $data->symbol);

        if ($asset === null) {
            throw ValidationException::withMessages([
                'asset_balance' => __('Insufficient available asset amount to cover this order.'),
            ]);
        }

        $amount = BigDecimal::of($data->amount);
        $available = BigDecimal::of($asset->amount);

        if ($available->isLessThan($amount)) {
            throw ValidationException::withMessages([
                'asset_balance' => __('Insufficient available asset amount to cover this order.'),
            ]);
        }

        $this->assetRepository->updateBalances(
            $asset,
            $available->minus($amount)->toScale(self::AssetScale)->__toString(),
            BigDecimal::of($asset->locked_amount)->plus($amount)->toScale(self::AssetScale)->__toString(),
        );

        return $this->orderRepository->create($data, OrderStatus::Open);
    }

    /**
     * Determine whether a persisted order matches the requested order details.
     *
     * Decimal values are compared by value so formatting differences (for
     * example "95000.0" versus the stored "95000.00") still count as a match.
     */
    private function matchesRequest(Order $order, CreateOrderData $data): bool
    {
        return $order->symbol === $data->symbol
            && $order->side === $data->side
            && BigDecimal::of($order->price)->isEqualTo($data->price)
            && BigDecimal::of($order->amount)->isEqualTo($data->amount);
    }
}
