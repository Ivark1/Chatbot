<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class Database
{
    private const SUPABASE_POSTGREST = '/rest/v1/';

    // SELECT - henter rader fra Supabase
    public static function select(string $table, array $filters = [], string $columns = '*'): ?array
    {
        $baseUrl = rtrim(SUPABASE_URL, '/') . self::SUPABASE_POSTGREST . $table;
        $params  = ['select=' . urlencode($columns)];

        foreach ($filters as $column => $value) {
            $params[] = sprintf('%s=eq.%s', $column, urlencode((string)$value));
        }

        $url = $baseUrl . '?' . implode('&', $params);

        $response = self::curlRequest($url, [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
        ]);

        return $response['status'] === 200 ? $response['data'] : null;
    }

    // INSERT - legger til en rad og returnerer representasjonen
    public static function insert(string $table, array $data): ?array
    {
        $url = rtrim(SUPABASE_URL, '/') . self::SUPABASE_POSTGREST . $table;

        $response = self::curlRequest($url, [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ], 'POST', $data);

        return in_array($response['status'], [200, 201], true) ? $response['data'] : null;
    }

    // UPDATE - Patch eksisterende rader basert på filters
    public static function update(string $table, array $filters, array $data): ?array
    {
        $baseUrl = rtrim(SUPABASE_URL, '/') . self::SUPABASE_POSTGREST . $table;
        $params  = [];

        foreach ($filters as $key => $value) {
            $params[] = sprintf('%s=eq.%s', $key, urlencode((string)$value));
        }

        $url = $baseUrl . '?' . implode('&', $params);

        $response = self::curlRequest($url, [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ], 'PATCH', $data);

        return $response['status'] === 200 ? $response['data'] : null;
    }

    // DELETE - sletter rader som matcher filter
    public static function delete(string $table, array $filters): bool
    {
        $baseUrl = rtrim(SUPABASE_URL, '/') . self::SUPABASE_POSTGREST . $table;
        $params  = [];

        foreach ($filters as $key => $value) {
            $params[] = sprintf('%s=eq.%s', $key, urlencode((string)$value));
        }

        $url = $baseUrl . '?' . implode('&', $params);

        $response = self::curlRequest($url, [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY
        ], 'DELETE');

        return $response['status'] === 204;
    }

    // Felles cURL wrapper brukt av alle queries
    private static function curlRequest(string $url, array $headers, string $method = 'GET', ?array $body = null): array
    {
        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        } elseif ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = json_encode($body);
            }
        }

        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);
        $data = json_last_error() === JSON_ERROR_NONE ? $decoded : null;

        return [
            'status' => $status,
            'data'   => $data
        ];
    }
}
