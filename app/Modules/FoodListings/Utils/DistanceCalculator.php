<?php

namespace App\Modules\FoodListings\Utils;

use Exception;

class DistanceCalculator
{
    /**
     * Calculate distance between two lat/lng pairs using Haversine formula.
     *
     * @param float $lat1 Origin latitude
     * @param float $lon1 Origin longitude
     * @param float $lat2 Destination latitude
     * @param float $lon2 Destination longitude
     * @return float Distance in kilometers
     */
    public static function inKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
