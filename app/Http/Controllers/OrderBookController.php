<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Orders\BuildOrderBook;
use App\DTOs\GetOrderBookData;
use App\Http\Requests\GetOrderBookRequest;
use App\Http\Resources\OrderBookResource;
use Illuminate\Http\JsonResponse;

class OrderBookController extends Controller
{
    public function __invoke(GetOrderBookRequest $request, BuildOrderBook $action): JsonResponse
    {
        $orderBook = $action->handle(GetOrderBookData::fromRequest($request));

        return OrderBookResource::make($orderBook)->response();
    }
}
