<?php
class LocationService
{
    const DEFAULT_LATITUDE = -15.3875;
    const DEFAULT_LONGITUDE = 28.3228;
    const DEFAULT_ZOOM = 12;
    const COUNTRY_ZOOM = 6;

    const HIGH_ACCURACY_THRESHOLD = 50;
    const MEDIUM_ACCURACY_THRESHOLD = 200;
    const LOW_ACCURACY_THRESHOLD = 1000;

    public static function getDefaultLocation() {
        return [
            'latitude' => self::DEFAULT_LATITUDE,
            'longitude' => self::DEFAULT_LONGITUDE,
            'city' => 'Lusaka',
            'country' => 'Zambia',
            'zoom' => self::DEFAULT_ZOOM,
            'accuracy' => null,
            'source' => 'default'
        ];
    }

    public static function getCountryView() {
        return [
            'latitude' => self::DEFAULT_LATITUDE,
            'longitude' => self::DEFAULT_LONGITUDE,
            'zoom' => self::COUNTRY_ZOOM,
            'bounds' => [
                'north' => -8.2,
                'south' => -18.1,
                'east' => 33.7,
                'west' => 21.9
            ]
        ];
    }

    public static function validateZambianCoordinates($latitude, $longitude) {
        $bounds = self::getCountryView()['bounds'];

        return (
            $latitude >= $bounds['south'] &&
            $latitude <= $bounds['north'] &&
            $longitude >= $bounds['west'] &&
            $longitude <= $bounds['east']
        );
    }

    public static function getAccuracyLevel($accuracy) {
        if ($accuracy === null) return 'unknown';
        if ($accuracy <= self::HIGH_ACCURACY_THRESHOLD) return 'high';
        if ($accuracy <= self::MEDIUM_ACCURACY_THRESHOLD) return 'medium';
        if ($accuracy <= self::LOW_ACCURACY_THRESHOLD) return 'low';
        return 'very_low';
    }

    public static function formatCoordinates($latitude, $longitude, $precision = 6) {
        return [
            'latitude' => round($latitude, $precision),
            'longitude' => round($longitude, $precision),
            'formatted' => round($latitude, $precision) . ', ' . round($longitude, $precision)
        ];
    }

    public static function calculateDistance($lat1, $lon1, $lat2, $lon2, $unit = 'km') {
        $earthRadius = ($unit === 'km') ? 6371 : 3959;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    public static function getZambianCities() {
        return [
            'lusaka' => [
                'name' => 'Lusaka',
                'latitude' => -15.3875,
                'longitude' => 28.3228,
                'province' => 'Lusaka'
            ],
            'kitwe' => [
                'name' => 'Kitwe',
                'latitude' => -12.8024,
                'longitude' => 28.2132,
                'province' => 'Copperbelt'
            ],
            'ndola' => [
                'name' => 'Ndola',
                'latitude' => -12.9587,
                'longitude' => 28.6366,
                'province' => 'Copperbelt'
            ],
            'kabwe' => [
                'name' => 'Kabwe',
                'latitude' => -14.4469,
                'longitude' => 28.4464,
                'province' => 'Central'
            ],
            'chingola' => [
                'name' => 'Chingola',
                'latitude' => -12.5289,
                'longitude' => 27.8818,
                'province' => 'Copperbelt'
            ],
            'mufulira' => [
                'name' => 'Mufulira',
                'latitude' => -12.5492,
                'longitude' => 28.2408,
                'province' => 'Copperbelt'
            ],
            'livingstone' => [
                'name' => 'Livingstone',
                'latitude' => -17.8419,
                'longitude' => 25.8561,
                'province' => 'Southern'
            ],
            'kasama' => [
                'name' => 'Kasama',
                'latitude' => -10.2127,
                'longitude' => 31.1808,
                'province' => 'Northern'
            ]
        ];
    }

    public static function findNearestCity($latitude, $longitude) {
        $cities = self::getZambianCities();
        $nearestCity = null;
        $shortestDistance = PHP_FLOAT_MAX;

        foreach ($cities as $key => $city) {
            $distance = self::calculateDistance(
                $latitude, $longitude,
                $city['latitude'], $city['longitude']
            );

            if ($distance < $shortestDistance) {
                $shortestDistance = $distance;
                $nearestCity = $city;
                $nearestCity['distance'] = $distance;
                $nearestCity['key'] = $key;
            }
        }

        return $nearestCity;
    }

    public static function getGPSConfiguration() {
        return [
            'default_location' => self::getDefaultLocation(),
            'country_bounds' => self::getCountryView()['bounds'],
            'cities' => self::getZambianCities(),
            'accuracy_thresholds' => [
                'high' => self::HIGH_ACCURACY_THRESHOLD,
                'medium' => self::MEDIUM_ACCURACY_THRESHOLD,
                'low' => self::LOW_ACCURACY_THRESHOLD
            ],
            'geolocation_options' => [
                'high_accuracy' => [
                    'enableHighAccuracy' => true,
                    'timeout' => 15000,
                    'maximumAge' => 5000
                ],
                'balanced' => [
                    'enableHighAccuracy' => false,
                    'timeout' => 10000,
                    'maximumAge' => 30000
                ],
                'fast' => [
                    'enableHighAccuracy' => false,
                    'timeout' => 5000,
                    'maximumAge' => 60000
                ]
            ]
        ];
    }
}
?>