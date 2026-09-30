<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Symbol;
use App\Models\Asset;

class AssetRepository
{
    /**
     * Retrieve a user's asset balance for a symbol with a write lock for the duration of the transaction.
     */
    public function findForUpdate(int $userId, Symbol $symbol): ?Asset
    {
        return Asset::query()
            ->where('user_id', $userId)
            ->where('symbol', $symbol->value)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Persist the asset's available and locked amounts.
     */
    public function updateBalances(Asset $asset, string $amount, string $lockedAmount): void
    {
        $asset->forceFill([
            'amount' => $amount,
            'locked_amount' => $lockedAmount,
        ])->save();
    }
}
