<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Symbol;
use App\Http\Requests\GetOrderBookRequest;

final readonly class GetOrderBookData
{
    public function __construct(public Symbol $symbol) {}

    public static function fromRequest(GetOrderBookRequest $request): self
    {
        return new self(Symbol::from((string) $request->validated('symbol')));
    }
}
