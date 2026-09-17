<?php

namespace App\Services;

use App\Models\Partner;

class DiscountService
{
    /**
     * Volume discount rate for an order, based on total units ordered.
     *
     * Tiers: 3% under 500 units, 7% from 500 to 999 units, 12% from 1000 units up.
     */
    public static function rateFor(Partner $partner, int $units): float
    {
        if ($units >= 1000) return 0.12;
        if ($units >= 500) return 0.07;

        return 0.03;
    }
}
