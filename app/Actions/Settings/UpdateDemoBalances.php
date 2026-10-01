<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTOs\DemoAssetBalanceData;
use App\DTOs\UpdateDemoBalancesData;
use App\Repositories\AssetRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;

class UpdateDemoBalances
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AssetRepository $assetRepository,
    ) {}

    /**
     * Overwrite the user's USD balances and asset balances.
     *
     * This backs the demo settings page and is intentionally unguarded beyond
     * the request validation: it exists only to seed a test account.
     */
    public function handle(UpdateDemoBalancesData $data): void
    {
        DB::transaction(function () use ($data): void {
            $user = $this->userRepository->lockById($data->userId);

            $this->userRepository->updateBalances($user, $data->balance, $data->lockedBalance);

            foreach ($data->assets as $asset) {
                $this->updateAsset($data->userId, $asset);
            }
        });
    }

    /**
     * Overwrite a single asset balance, creating the row when it does not exist.
     */
    private function updateAsset(int $userId, DemoAssetBalanceData $data): void
    {
        $asset = $this->assetRepository->findOrCreateForUpdate($userId, $data->symbol);

        $this->assetRepository->updateBalances($asset, $data->amount, $data->lockedAmount);
    }
}
