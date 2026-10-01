<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProfileResource;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Show the authenticated user's USD and asset balances.
     */
    public function show(Request $request, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->findWithAssets((int) $request->user()->id);

        return ProfileResource::make($user)->response();
    }
}
