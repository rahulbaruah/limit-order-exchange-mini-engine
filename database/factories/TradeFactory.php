<?php

namespace Database\Factories;

use App\Enums\Symbol;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trade>
 */
class TradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buy_order_id' => Order::factory()->buy(),
            'sell_order_id' => Order::factory()->sell(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'symbol' => Symbol::Btc,
            'price' => '95000.00',
            'amount' => '0.01000000',
            'gross_amount' => '950.00',
            'fee' => '14.25',
        ];
    }
}
