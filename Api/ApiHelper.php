<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class ApiHelper
{
    private const GEO_URL    = 'https://api.geoapify.com/v1/geocode/search';
    private const PLACES_URL = 'https://api.geoapify.com/v2/places';
    private const METEO_URL  = 'https://api.open-meteo.com/v1/forecast';

    // Debug er False(AV) i produksjon
    private const DEBUG = false;

    // Geoapify: Gjør om adresse til koordinater
         public static function geocodeAddress(string $address): ?array
    {
        if ($address === '') {
            return null;
        }

        $url = self::GEO_URL . '?' . http_build_query([
            'text'   => $address,
            'apiKey' => GEOAPIFY_API_KEY,
            'limit'  => 5,
            'format' => 'json',
        ]);

        $json = self::curlGet($url);

        if (!$json || empty($json['results'][0])) {
            return null;
        }

        $result = $json['results'][0];
        $lat    = $result['lat'] ?? null;
        $lon    = $result['lon'] ?? null;

        if ($lat === null || $lon === null) {
            return null;
        }

        return [
            'lat'       => (float)$lat,
            'lon'       => (float)$lon,
            'formatted' => $result['formatted'] ?? ($result['city'] ?? $address),
        ];
    }

    // Geoapify: Finner attraksjoner/severdigheter/restauranter
    public static function getPlaces(float $lat, float $lon, string $category): ?array
    {
        $url = self::PLACES_URL . '?' . http_build_query([
            'categories' => $category,
            'filter'     => "circle:$lon,$lat,3000",
            'limit'      => 20,
            'apiKey'     => GEOAPIFY_API_KEY,
        ]);

        return self::curlGet($url);
    }

    // Open-Meteo Weather API: Henter vær-melding
    public static function getWeather(float $lat, float $lon, int $forecastDays = 7): ?array
    {
        $forecastDays = max(1, min($forecastDays, 7));

        $url = self::METEO_URL . '?' . http_build_query([
            'latitude'      => $lat,
            'longitude'     => $lon,
            'current'       => 'temperature_2m,wind_speed_10m,weather_code',
            'daily'         => 'temperature_2m_max,temperature_2m_min,precipitation_sum',
            'forecast_days' => $forecastDays,
            'timezone'      => 'auto',
        ]);

        return self::curlGet($url);
    }

    // Tillegsfunksjon for prompt til Gemini
    public static function getGeoDataForPrompt(string $query): ?string
    {
        $geo = self::geocodeAddress($query);
        if (!$geo) {
            return null;
        }

        return "Sted: {$geo['formatted']} (lat {$geo['lat']}, lon {$geo['lon']})";
    }

    // Tillegsfunksjon for prompt til Gemini
    public static function getWeatherForPrompt(string $query): ?string
    {
        $geo = self::geocodeAddress($query);
        if (!$geo) {
            return null;
        }

        $weather = self::getWeather($geo['lat'], $geo['lon'], 1);
        if (!$weather || empty($weather['current'])) {
            return null;
        }

        $c        = $weather['current'];
        $temp     = $c['temperature_2m']   ?? null;
        $windKmh  = $c['wind_speed_10m']   ?? null;
        $windMs   = $windKmh !== null ? round($windKmh / 3.6, 1) : null;
        $wcode    = $c['weather_code']     ?? null;

        $parts = [];
        if ($temp !== null)   $parts[] = "Temperatur: {$temp}°C";
        if ($windMs !== null) $parts[] = "Vind: {$windMs} m/s";
        if ($wcode !== null)  $parts[] = "Værkode: {$wcode}";

        return implode(', ', $parts);
    }

    // Ekstraktør for sted
    public static function extractLocation(string $text): ?string
    {
        $possible = [
            'Oslo','Bergen','Trondheim','Stavanger','Kristiansand',
            'Tromsø','Bodø','Ålesund','Drammen'
        ];

        foreach ($possible as $city) {
            if (stripos($text, $city) !== false) {
                return $city;
            }
        }

        return null;
    }

    // Ekstraktør for sted
    public static function extractPlace(string $text): ?string
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        if (!preg_match_all('/\b([A-ZÆØÅ][a-zA-ZæøåÆØÅ]+)\b/u', $clean, $matches)) {
            return null;
        }

        $ignore = [
            'Jeg','Hva','Hvor','Hvordan','Skal','Kan','Du','Vi',
            'Den','Det','Og','Men','For','Hei','Takk'
        ];

        foreach ($matches[1] as $word) {
            if (!in_array($word, $ignore, true)) {
                return $word;
            }
        }

        return null;
    }

    // cURL wrapper
    private static function curlGet(string $url): ?array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($error || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        $json = json_decode($response, true);
        return $json === null && json_last_error() !== JSON_ERROR_NONE ? null : $json;
    }
}
