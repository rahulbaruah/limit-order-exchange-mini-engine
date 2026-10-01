<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Concerns\CalculatesOrderAmounts;
use App\DTOs\CreateOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Repositories\AssetRepository;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use App\Services\OrderMatchingService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    use CalculatesOrderAmounts;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly AssetRepository $assetRepository,
        private readonly OrderMatchingService $matchingService,
    ) {}

    /**
     * Create an open limit order, reserving the funds or assets it commits, then match it.
     *
     * A buy reserves the notional plus the upfront fee from the USD balance; a
     * sell moves the ordered amount from the available asset balance into the
     * asset's locked amount. The order is then offered to the matching engine
     * and comes back filled when a compatible counter-order existed.
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

            $order = match ($data->side) {
                OrderSide::Buy => $this->createBuyOrder($user, $data),
                OrderSide::Sell => $this->createSellOrder($data),
            };

            $this->matchingService->match($order);

            return $order;
        });
    }

    /**
     * Reserve the notional plus the upfront fee and create an open buy order.
     *
     * @throws ValidationException When the available balance cannot cover the order.
     */
    private function createBuyOrder(User $user, CreateOrderData $data): Order
    {
        $notional = $this->notionalFor($data->price, $data->amount);
        $fee = $this->feeFor($data->price, $data->amount);
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
                ->plus($required)
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
