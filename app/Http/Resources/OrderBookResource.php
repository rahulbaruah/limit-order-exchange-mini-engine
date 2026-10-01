<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\OrderBookData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderBookData */
class OrderBookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderBookData $orderBook */
        $orderBook = $this->resource;

        return $orderBook->toArray();
    }
}
