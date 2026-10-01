<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    /**
     * Retrieve a user together with their asset balances.
     */
    public function findWithAssets(int $userId): User
    {
        return User::query()->with('assets')->findOrFail($userId);
    }

    /**
     * Retrieve a user by ID with a write lock for the duration of the transaction.
     */
    public function lockById(int $userId): User
    {
        return User::query()->lockForUpdate()->findOrFail($userId);
    }

    /**
     * Lock users in ascending ID order for the duration of the transaction.
     *
     * @return Collection<int, User>
     */
    public function lockByIds(int ...$userIds): Collection
    {
        $userIds = array_values(array_unique($userIds));
        sort($userIds, SORT_NUMERIC);

        $users = new Collection;

        foreach ($userIds as $userId) {
            $users->put($userId, $this->lockById($userId));
        }

        return $users;
    }

    /**
     * Persist the user's USD balance and locked USD balance.
     */
    public function updateBalances(User $user, string $balance, string $lockedBalance): void
    {
        $user->forceFill([
            'balance' => $balance,
            'locked_balance' => $lockedBalance,
        ])->save();
    }
}
