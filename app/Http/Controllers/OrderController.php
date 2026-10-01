<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CreateOrder;
use App\DTOs\CancelOrderData;
use App\DTOs\CreateOrderData;
use App\DTOs\ListOrdersData;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Repositories\OrderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * List the authenticated user's orders for the requested symbol and optional filters.
     */
    public function index(ListOrdersRequest $request, OrderRepository $orderRepository): JsonResponse
    {
        $filters = ListOrdersData::fromRequest($request);

        return OrderResource::collection(
            $orderRepository->visibleForUser((int) $request->user()->id, $filters),
        )->response();
    }

    /**
     * Store a newly created limit order for the authenticated user.
     */
    public function store(CreateOrderRequest $request, CreateOrder $action): JsonResponse
    {
        $order = $action->handle(CreateOrderData::fromRequest($request));

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    /**
     * Cancel an open order owned by the authenticated user.
     */
    public function cancel(Request $request, CancelOrder $action): JsonResponse
    {
        $order = $action->handle(CancelOrderData::fromRequest($request));

        return OrderResource::make($order)->response();
    }
}
