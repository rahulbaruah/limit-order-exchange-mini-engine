<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\DTOs\CreateOrderData;
use App\Enums\OrderStatus;
use App\Models\Order;
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
     * Fee rate charged on the order notional.
     */
    private const string FeeRate = '0.015';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
    ) {}

    /**
     * Create an open buy order, reserving the notional and charging the fee upfront.
     *
     * @throws ValidationException When the available balance cannot cover the order.
     */
    public function handle(CreateOrderData $data): Order
    {
        $rawNotional = BigDecimal::of($data->price)->multipliedBy($data->amount);

        $notional = $rawNotional->toScale(self::UsdScale, RoundingMode::Ceiling);
        $fee = $rawNotional
            ->multipliedBy(self::FeeRate)
            ->toScale(self::UsdScale, RoundingMode::Ceiling);
        $required = $notional->plus($fee);

        return DB::transaction(function () use ($data, $notional, $required): Order {
            $user = $this->userRepository->lockById($data->userId);
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
        });
    }
}
