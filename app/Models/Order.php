<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Symbol $symbol
 * @property OrderSide $side
 * @property string $price
 * @property string $amount
 * @property OrderStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Trade> $buyTrades
 * @property-read Collection<int, Trade> $sellTrades
 */
#[Fillable(['user_id', 'symbol', 'side', 'price', 'amount', 'status'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'symbol' => Symbol::class,
            'side' => OrderSide::class,
            'price' => 'decimal:2',
            'amount' => 'decimal:8',
            'status' => OrderStatus::class,
        ];
    }

    /**
     * Get the user that placed the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trades where this order was the buy side.
     *
     * @return HasMany<Trade, $this>
     */
    public function buyTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'buy_order_id');
    }

    /**
     * Get the trades where this order was the sell side.
     *
     * @return HasMany<Trade, $this>
     */
    public function sellTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'sell_order_id');
    }
}
