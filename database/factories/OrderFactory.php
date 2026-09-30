<?php

namespace Database\Factories;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => Symbol::Btc,
            'side' => OrderSide::Buy,
            'price' => '95000.00',
            'amount' => '0.01000000',
            'status' => OrderStatus::Open,
        ];
    }

    /**
     * Indicate that the order is a buy.
     */
    public function buy(): static
    {
        return $this->state(fn (array $attributes): array => [
            'side' => OrderSide::Buy,
        ]);
    }

    /**
     * Indicate that the order is a sell.
     */
    public function sell(): static
    {
        return $this->state(fn (array $attributes): array => [
            'side' => OrderSide::Sell,
        ]);
    }

    /**
     * Indicate that the order has been filled.
     */
    public function filled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Filled,
        ]);
    }

    /**
     * Indicate that the order has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Cancelled,
        ]);
    }

    /**
     * Indicate that the order is for the given symbol.
     */
    public function forSymbol(Symbol $symbol): static
    {
        return $this->state(fn (array $attributes): array => [
            'symbol' => $symbol,
        ]);
    }
}
