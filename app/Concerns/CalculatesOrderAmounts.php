<?php

declare(strict_types=1);

namespace App\Concerns;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Shared money maths so reservation, settlement, and release stay in sync.
 */
trait CalculatesOrderAmounts
{
    /**
     * USD amounts are stored with two decimal places.
     */
    private const int UsdScale = 2;

    /**
     * Asset amounts are stored with eight decimal places.
     */
    private const int AssetScale = 8;

    /**
     * Fee rate charged on the order notional.
     */
    private const string FeeRate = '0.015';

    /**
     * Calculate the USD value of an amount of assets at the given price.
     *
     * Fractional cents are rounded up so the exchange is never short.
     */
    private function notionalFor(string $price, string $amount): BigDecimal
    {
        return BigDecimal::of($price)
            ->multipliedBy($amount)
            ->toScale(self::UsdScale, RoundingMode::Ceiling);
    }

    /**
     * Calculate the commission charged on an amount of assets at the given price.
     */
    private function feeFor(string $price, string $amount): BigDecimal
    {
        return BigDecimal::of($price)
            ->multipliedBy($amount)
            ->multipliedBy(self::FeeRate)
            ->toScale(self::UsdScale, RoundingMode::Ceiling);
    }
}
