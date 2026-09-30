<?php

namespace App\Models;

use App\Enums\Symbol;
use Database\Factories\TradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $buy_order_id
 * @property int $sell_order_id
 * @property int $buyer_id
 * @property int $seller_id
 * @property Symbol $symbol
 * @property string $price
 * @property string $amount
 * @property string $gross_amount
 * @property string $fee
 * @property Carbon|null $created_at
 * @property-read Order $buyOrder
 * @property-read Order $sellOrder
 * @property-read User $buyer
 * @property-read User $seller
 */
#[Fillable(['buy_order_id', 'sell_order_id', 'buyer_id', 'seller_id', 'symbol', 'price', 'amount', 'gross_amount', 'fee'])]
class Trade extends Model
{
    /** @use HasFactory<TradeFactory> */
    use HasFactory;

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'symbol' => Symbol::class,
            'price' => 'decimal:2',
            'amount' => 'decimal:8',
            'gross_amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the matched buy order.
     *
     * @return BelongsTo<Order, $this>
     */
    public function buyOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'buy_order_id');
    }

    /**
     * Get the matched sell order.
     *
     * @return BelongsTo<Order, $this>
     */
    public function sellOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'sell_order_id');
    }

    /**
     * Get the user that bought the asset.
     *
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the user that sold the asset.
     *
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
