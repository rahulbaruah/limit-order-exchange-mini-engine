<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateDemoBalances;
use App\DTOs\UpdateDemoBalancesData;
use App\Enums\Symbol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DemoUpdateRequest;
use App\Http\Resources\ProfileResource;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DemoController extends Controller
{
    /**
     * Show the demo balances page.
     */
    public function edit(Request $request, UserRepository $userRepository): Response
    {
        $user = $userRepository->findWithAssets((int) $request->user()->id);

        return Inertia::render('settings/Demo', [
            'balances' => ProfileResource::make($user)->resolve($request),
            'symbols' => array_map(
                static fn (Symbol $symbol): string => $symbol->value,
                Symbol::cases(),
            ),
        ]);
    }

    /**
     * Overwrite the authenticated user's USD and asset balances.
     */
    public function update(DemoUpdateRequest $request, UpdateDemoBalances $action): RedirectResponse
    {
        $action->handle(UpdateDemoBalancesData::fromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Demo balances updated.')]);

        return to_route('demo.edit');
    }
}
