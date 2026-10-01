<?php

declare(strict_types=1);

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CreateOrder;
use App\DTOs\CancelOrderData;
use App\DTOs\CreateOrderData;
use App\Enums\OrderSide;
use App\Enums\Symbol;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payload = json_decode((string) getenv('ORDER_CONCURRENCY_PAYLOAD'), true, flags: JSON_THROW_ON_ERROR);
$readyFile = (string) getenv('ORDER_CONCURRENCY_READY_FILE');
$startFile = (string) getenv('ORDER_CONCURRENCY_START_FILE');
$resultFile = (string) getenv('ORDER_CONCURRENCY_RESULT_FILE');

file_put_contents($readyFile, 'ready', LOCK_EX);

$deadline = microtime(true) + 20;

while (! is_file($startFile)) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Concurrency worker timed out at the start barrier.');
    }

    usleep(10_000);
}

if ($payload['action'] === 'create') {
    $order = $app->make(CreateOrder::class)->handle(new CreateOrderData(
        userId: (int) $payload['user_id'],
        symbol: Symbol::from((string) $payload['symbol']),
        side: OrderSide::from((string) $payload['side']),
        price: (string) $payload['price'],
        amount: (string) $payload['amount'],
        idempotencyKey: (string) $payload['idempotency_key'],
    ));

    $result = [
        'action' => 'create',
        'outcome' => 'created',
        'order_id' => (int) $order->id,
        'order_status' => $order->status->value,
    ];
} elseif ($payload['action'] === 'cancel') {
    try {
        $order = $app->make(CancelOrder::class)->handle(new CancelOrderData(
            userId: (int) $payload['user_id'],
            orderId: (int) $payload['order_id'],
        ));

        $result = [
            'action' => 'cancel',
            'outcome' => 'cancelled',
            'order_id' => (int) $order->id,
            'order_status' => $order->status->value,
        ];
    } catch (HttpExceptionInterface $exception) {
        if ($exception->getStatusCode() !== 409) {
            throw $exception;
        }

        $result = [
            'action' => 'cancel',
            'outcome' => 'conflict',
        ];
    }
} else {
    throw new InvalidArgumentException('Unsupported concurrency worker action.');
}

file_put_contents($resultFile, json_encode($result, JSON_THROW_ON_ERROR), LOCK_EX);
