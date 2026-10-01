<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'usd' => [
                'balance' => $this->balance,
                'locked_balance' => $this->locked_balance,
            ],
            'assets' => $this->assets
                ->sortBy('id')
                ->values()
                ->map(fn (Asset $asset): array => [
                    'symbol' => $asset->symbol->value,
                    'amount' => $asset->amount,
                    'locked_amount' => $asset->locked_amount,
                ])
                ->all(),
        ];
    }
}
