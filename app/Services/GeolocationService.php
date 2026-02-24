<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeolocationService
{
    private const API_URL = 'https://ipapi.co/{ip}/json/';

    /**
     * Detect lat/lng from an IP address via ipapi.co.
     * Returns ['lat', 'lng', 'city', 'country'] or null on failure.
     */
    public function detectFromIp(string $ip): ?array
    {
        // Skip private/local IPs
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        try {
            $url      = str_replace('{ip}', $ip, self::API_URL);
            $response = Http::timeout(3)->get($url);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['latitude'], $data['longitude'])) {
                    return [
                        'lat'     => (float) $data['latitude'],
                        'lng'     => (float) $data['longitude'],
                        'city'    => $data['city'] ?? null,
                        'country' => $data['country_name'] ?? null,
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
}
