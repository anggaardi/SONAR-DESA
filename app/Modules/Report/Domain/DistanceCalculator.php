<?php

declare(strict_types=1);

namespace App\Modules\Report\Domain;

/**
 * Great-circle distance between two WGS84 points (haversine). Pure PHP, no framework.
 * PRD Arsitektur M4-03.
 */
final class DistanceCalculator
{
    /** IUGG mean Earth radius in meters. */
    private const EARTH_RADIUS_M = 6_371_008.8;

    public function meters(float $latFrom, float $lngFrom, float $latTo, float $lngTo): float
    {
        $latFromRad = deg2rad($latFrom);
        $latToRad = deg2rad($latTo);
        $latDelta = $latToRad - $latFromRad;
        $lngDelta = deg2rad($lngTo - $lngFrom);

        $a = sin($latDelta / 2) ** 2
            + cos($latFromRad) * cos($latToRad) * sin($lngDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }
}
