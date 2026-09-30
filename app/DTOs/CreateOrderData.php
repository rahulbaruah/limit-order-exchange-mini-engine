<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\OrderSide;
use App\Enums\Symbol;
use App\Http\Requests\CreateOrderRequest;

final readonly class CreateOrderData
{
    public function __construct(
        public int $userId,
        public Symbol $symbol,
        public OrderSide $side,
        public string $price,
        public string $amount,
        public string $idempotencyKey,
    ) {}

    /**
     * Build the DTO from validated request input.
     */
    public static function fromRequest(CreateOrderRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            userId: (int) $request->user()->id,
            symbol: Symbol::from((string) $validated['symbol']),
            side: OrderSide::from((string) $validated['side']),
            price: self::normaliseNumericInput($validated['price'], 2),
            amount: self::normaliseNumericInput($validated['amount'], 8),
            idempotencyKey: (string) $validated['idempotency_key'],
        );
    }

    /**
     * Map the DTO to order model attributes.
     *
     * @return array{user_id: int, symbol: string, side: string, price: string, amount: string, idempotency_key: string}
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'symbol' => $this->symbol->value,
            'side' => $this->side->value,
            'price' => $this->price,
            'amount' => $this->amount,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }

    /**
     * Normalise a validated numeric value to a canonical decimal string.
     *
     * JSON numbers decode to floats, and casting a small float to string can
     * yield exponent notation (e.g. "1.0E-8"). Format at the schema scale so
     * the value is stored and returned in plain decimal form.
     */
    private static function normaliseNumericInput(mixed $value, int $scale): string
    {
        if (is_string($value)) {
            return $value;
        }

        return number_format((float) $value, $scale, '.', '');
    }
}
