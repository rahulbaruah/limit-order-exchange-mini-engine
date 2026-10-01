<?php

declare(strict_types=1);

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

test('concurrent reciprocal matches settle one trade without deadlock', function () {
    assertOrderConcurrencyDatabase();

    $userIds = [];

    try {
        $seller = User::factory()->funded('0.00')->create();
        $userIds[] = (int) $seller->id;
        $buyer = User::factory()->funded('99035.75')->create(['locked_balance' => '964.25']);
        $userIds[] = (int) $buyer->id;

        Asset::factory()->for($seller)->locked('0.01000000')->create([
            'symbol' => Symbol::Btc,
            'amount' => '0.01000000',
        ]);

        Order::factory()->sell()->for($seller)->create([
            'symbol' => Symbol::Btc,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);

        Order::factory()->buy()->for($buyer)->create([
            'symbol' => Symbol::Btc,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);

        $results = runOrderConcurrencyWorkers([
            [
                'action' => 'create',
                'user_id' => (int) $buyer->id,
                'symbol' => Symbol::Btc->value,
                'side' => OrderSide::Buy->value,
                'price' => '95000.00',
                'amount' => '0.01000000',
                'idempotency_key' => 'concurrent-buy-'.Str::uuid(),
            ],
            [
                'action' => 'create',
                'user_id' => (int) $seller->id,
                'symbol' => Symbol::Btc->value,
                'side' => OrderSide::Sell->value,
                'price' => '95000.00',
                'amount' => '0.01000000',
                'idempotency_key' => 'concurrent-sell-'.Str::uuid(),
            ],
        ]);

        expect(collect($results)->pluck('outcome')->unique()->all())->toBe(['created']);

        $orders = Order::query()->whereIn('user_id', $userIds)->get();
        $trades = Trade::query()
            ->whereIn('buyer_id', $userIds)
            ->whereIn('seller_id', $userIds)
            ->get();
        $tradedOrderIds = $trades
            ->flatMap(fn (Trade $trade): array => [$trade->buy_order_id, $trade->sell_order_id])
            ->unique();

        expect($orders)->toHaveCount(4)
            ->and($orders->where('status', OrderStatus::Filled))->toHaveCount(4)
            ->and($trades)->toHaveCount(2)
            ->and($tradedOrderIds)->toHaveCount(4)
            ->and($trades->pluck('price')->unique())->toHaveCount(1)
            ->and($trades->pluck('price')->sole())->toBe('95000.00')
            ->and($trades->pluck('amount')->unique())->toHaveCount(1)
            ->and($trades->pluck('amount')->sole())->toBe('0.01000000');

        expect($buyer->refresh()->balance)->toBe('98071.50')
            ->and($buyer->locked_balance)->toBe('0.00')
            ->and($buyer->assets()->sole()->amount)->toBe('0.02000000');

        $sellerAsset = $seller->refresh()->assets()->sole();

        expect($seller->balance)->toBe('1900.00')
            ->and($sellerAsset->amount)->toBe('0.00000000')
            ->and($sellerAsset->locked_amount)->toBe('0.00000000');
    } finally {
        cleanupOrderConcurrencyUsers($userIds);
    }
});

test('a concurrent cancellation and match produce one consistent outcome', function () {
    assertOrderConcurrencyDatabase();

    $userIds = [];

    try {
        $buyer = User::factory()->funded('99035.75')->create(['locked_balance' => '964.25']);
        $userIds[] = (int) $buyer->id;
        $seller = User::factory()->funded('0.00')->create();
        $userIds[] = (int) $seller->id;

        Asset::factory()->for($seller)->create([
            'symbol' => Symbol::Btc,
            'amount' => '0.01000000',
        ]);

        $buyOrder = Order::factory()->buy()->for($buyer)->create([
            'symbol' => Symbol::Btc,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);

        $results = runOrderConcurrencyWorkers([
            [
                'action' => 'create',
                'user_id' => (int) $seller->id,
                'symbol' => Symbol::Btc->value,
                'side' => OrderSide::Sell->value,
                'price' => '95000.00',
                'amount' => '0.01000000',
                'idempotency_key' => 'race-sell-'.Str::uuid(),
            ],
            [
                'action' => 'cancel',
                'user_id' => (int) $buyer->id,
                'order_id' => (int) $buyOrder->id,
            ],
        ]);

        $createResult = collect($results)->firstWhere('action', 'create');
        $cancelResult = collect($results)->firstWhere('action', 'cancel');

        expect($createResult['outcome'])->toBe('created')
            ->and($cancelResult['outcome'])->toBeIn(['cancelled', 'conflict']);

        $buyOrder->refresh();
        $seller->refresh();
        $sellerAsset = $seller->assets()->sole();
        $tradeCount = Trade::query()
            ->where('buyer_id', $buyer->id)
            ->where('seller_id', $seller->id)
            ->count();

        if ($cancelResult['outcome'] === 'cancelled') {
            expect($buyOrder->status)->toBe(OrderStatus::Cancelled)
                ->and($tradeCount)->toBe(0)
                ->and($seller->balance)->toBe('0.00')
                ->and($sellerAsset->amount)->toBe('0.00000000')
                ->and($sellerAsset->locked_amount)->toBe('0.01000000');
        } else {
            expect($buyOrder->status)->toBe(OrderStatus::Filled)
                ->and($tradeCount)->toBe(1)
                ->and($seller->balance)->toBe('950.00')
                ->and($sellerAsset->amount)->toBe('0.00000000')
                ->and($sellerAsset->locked_amount)->toBe('0.00000000');
        }
    } finally {
        cleanupOrderConcurrencyUsers($userIds);
    }
});

function assertOrderConcurrencyDatabase(): void
{
    if (DB::connection()->getDriverName() !== 'mysql'
        || DB::connection()->getDatabaseName() !== 'limit_order_exchange_concurrency_test') {
        throw new RuntimeException('Concurrency tests require the dedicated MySQL test database.');
    }
}

/**
 * @param  list<array<string, int|string>>  $workers
 * @return list<array{action: string, outcome: string, order_id?: int, order_status?: string}>
 */
function runOrderConcurrencyWorkers(array $workers): array
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'order-concurrency-'.Str::uuid();
    mkdir($directory);

    $startFile = $directory.DIRECTORY_SEPARATOR.'start';
    $processes = [];

    try {
        foreach ($workers as $index => $payload) {
            $readyFile = $directory.DIRECTORY_SEPARATOR."ready-{$index}";
            $resultFile = $directory.DIRECTORY_SEPARATOR."result-{$index}.json";
            $environment = [
                'APP_ENV' => 'testing',
                'APP_KEY' => (string) config('app.key'),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) config('database.connections.mysql.host'),
                'DB_PORT' => (string) config('database.connections.mysql.port'),
                'DB_DATABASE' => (string) config('database.connections.mysql.database'),
                'DB_USERNAME' => (string) config('database.connections.mysql.username'),
                'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
                'DB_URL' => '',
                'BROADCAST_CONNECTION' => 'null',
                'CACHE_STORE' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'SESSION_DRIVER' => 'array',
                'ORDER_CONCURRENCY_PAYLOAD' => json_encode($payload, JSON_THROW_ON_ERROR),
                'ORDER_CONCURRENCY_READY_FILE' => $readyFile,
                'ORDER_CONCURRENCY_START_FILE' => $startFile,
                'ORDER_CONCURRENCY_RESULT_FILE' => $resultFile,
            ];

            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/OrderConcurrencyWorker.php'),
            ], base_path(), $environment);
            $process->setTimeout(45);
            $process->start();
            $processes[] = [$index, $process, $readyFile, $resultFile];
        }

        $deadline = microtime(true) + 20;

        while (microtime(true) < $deadline) {
            if (collect($processes)->every(fn (array $worker): bool => is_file($worker[2]))) {
                break;
            }

            usleep(10_000);
        }

        if (! collect($processes)->every(fn (array $worker): bool => is_file($worker[2]))) {
            throw new RuntimeException('Concurrency workers did not reach the start barrier.');
        }

        file_put_contents($startFile, 'start');

        $results = [];

        foreach ($processes as [$index, $process, , $resultFile]) {
            $process->wait();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(sprintf(
                    "Concurrency worker %d failed (exit code %s).\nSTDERR:\n%s\nSTDOUT:\n%s",
                    $index,
                    $process->getExitCode() ?? 'unknown',
                    $process->getErrorOutput(),
                    $process->getOutput(),
                ));
            }

            if (! is_file($resultFile)) {
                throw new RuntimeException(sprintf(
                    "Concurrency worker %d exited successfully without writing its result file.\nSTDERR:\n%s\nSTDOUT:\n%s",
                    $index,
                    $process->getErrorOutput(),
                    $process->getOutput(),
                ));
            }

            $results[] = json_decode((string) file_get_contents($resultFile), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as [, $process]) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            unlink($path);
        }

        rmdir($directory);
    }
}

/**
 * Remove all rows created for the supplied concurrency-test users.
 *
 * @param  list<int>  $userIds
 */
function cleanupOrderConcurrencyUsers(array $userIds): void
{
    if ($userIds === []) {
        return;
    }

    Trade::query()
        ->whereIn('buyer_id', $userIds)
        ->orWhereIn('seller_id', $userIds)
        ->delete();

    Order::query()->whereIn('user_id', $userIds)->delete();
    Asset::query()->whereIn('user_id', $userIds)->delete();
    User::query()->whereKey($userIds)->delete();
}
