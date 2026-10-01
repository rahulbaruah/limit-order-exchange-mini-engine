<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Symbol;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Starting USD balance granted to the development accounts.
     */
    private const string StartingBalance = '100000.00';

    /**
     * Starting asset amounts granted to the sell testing account.
     *
     * @var list<array{0: Symbol, 1: string}>
     */
    private const array StartingAssets = [
        [Symbol::Btc, '1.00000000'],
        [Symbol::Eth, '10.00000000'],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedUser('buyer@example.com', 'Buy Tester');

        $seller = $this->seedUser('seller@example.com', 'Sell Tester');

        foreach (self::StartingAssets as [$symbol, $amount]) {
            Asset::query()->updateOrCreate(
                [
                    'user_id' => $seller->id,
                    'symbol' => $symbol,
                ],
                [
                    'amount' => $amount,
                    'locked_amount' => '0.00000000',
                ],
            );
        }
    }

    /**
     * Find or create a funded development account for the given email.
     */
    private function seedUser(string $email, string $name): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->name = $name;
        $user->balance = self::StartingBalance;

        if (! $user->exists) {
            $user->password = 'password';
            $user->email_verified_at = now();
        }

        $user->save();

        return $user;
    }
}
