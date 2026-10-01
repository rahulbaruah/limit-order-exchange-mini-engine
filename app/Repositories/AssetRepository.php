<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Symbol;
use App\Models\Asset;
use RuntimeException;

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
     * Retrieve a user's asset balance for a symbol, creating an empty one first when absent.
     */
    public function findOrCreateForUpdate(int $userId, Symbol $symbol): Asset
    {
        Asset::query()->firstOrCreate(
            ['user_id' => $userId, 'symbol' => $symbol->value],
            ['amount' => '0.00000000', 'locked_amount' => '0.00000000'],
        );

        return $this->findForUpdate($userId, $symbol)
            ?? throw new RuntimeException('The asset balance could not be created.');
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
