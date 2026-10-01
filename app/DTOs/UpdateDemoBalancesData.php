<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\Settings\DemoUpdateRequest;

final readonly class UpdateDemoBalancesData
{
    /**
     * @param  list<DemoAssetBalanceData>  $assets
     */
    public function __construct(
        public int $userId,
        public string $balance,
        public string $lockedBalance,
        public array $assets,
    ) {}

    /**
     * Build the DTO from validated request input.
     */
    public static function fromRequest(DemoUpdateRequest $request): self
    {
        $validated = $request->validated();

        /** @var list<array<string, mixed>> $assets */
        $assets = $validated['assets'];

        return new self(
            userId: (int) $request->user()->id,
            balance: (string) $validated['balance'],
            lockedBalance: (string) $validated['locked_balance'],
            assets: array_map(
                static fn (array $asset): DemoAssetBalanceData => DemoAssetBalanceData::fromArray($asset),
                $assets,
            ),
        );
    }
}
