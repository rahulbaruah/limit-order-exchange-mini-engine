<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Symbol;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
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
            'amount' => '0.00000000',
            'locked_amount' => '0.00000000',
        ];
    }

    /**
     * Indicate that the balance is held in Ethereum.
     */
    public function eth(): static
    {
        return $this->state(fn (array $attributes): array => [
            'symbol' => Symbol::Eth,
        ]);
    }

    /**
     * Indicate that part of the balance is reserved by an open order.
     */
    public function locked(string $lockedAmount = '1.00000000'): static
    {
        return $this->state(fn (array $attributes): array => [
            'locked_amount' => $lockedAmount,
        ]);
    }
}
