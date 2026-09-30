<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    /**
     * Retrieve a user by ID with a write lock for the duration of the transaction.
     */
    public function lockById(int $userId): User
    {
        return User::query()->lockForUpdate()->findOrFail($userId);
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
