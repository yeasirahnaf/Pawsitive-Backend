<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeolocationService
{
    private const IP_URL      = 'https://api.bigdatacloud.net/data/ip-geolocation';
    private const REVERSE_URL = 'https://api.bigdatacloud.net/data/reverse-geocode-client';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.bigdatacloud.key', '');
    }

    /**
     * Detect lat/lng from an IP address via BigDataCloud.
     * Used as a server-side fallback when the browser does not send coordinates.
     * Returns ['lat', 'lng', 'city', 'country'] or null on failure.
     */
    public function detectFromIp(string $ip): ?array
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        try {
            $response = Http::timeout(3)->get(self::IP_URL, [
                'ip'             => $ip,
                'key'            => $this->apiKey,
                'localityLanguage' => 'en',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $lat = $data['location']['latitude']  ?? null;
                $lng = $data['location']['longitude'] ?? null;

                if ($lat !== null && $lng !== null) {
                    return [
                        'lat'     => (float) $lat,
                        'lng'     => (float) $lng,
                        'city'    => $data['location']['city']    ?? null,
                        'country' => $data['country']['name']     ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GeolocationService: IP lookup failed', [
                'ip'    => $ip,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Reverse geocode browser-provided coordinates to a human-readable city name.
     * The client-side endpoint does NOT require an API key.
     * Returns ['city', 'locality', 'country'] or null on failure.
     */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        try {
            $response = Http::timeout(3)->get(self::REVERSE_URL, [
                'latitude'         => $lat,
                'longitude'        => $lng,
                'localityLanguage' => 'en',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'city'     => $data['city']       ?? $data['locality'] ?? null,
                    'locality' => $data['locality']   ?? null,
                    'country'  => $data['countryName'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('GeolocationService: reverse geocode failed', [
                'lat'   => $lat,
                'lng'   => $lng,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
