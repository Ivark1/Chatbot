<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class ChatModel
{
    private const MODEL   = 'gemini-2.5-flash';
    private const API_URL = 'https://generativelanguage.googleapis.com/v1/models/';

    // Sender en prompt til Gemini-modellen og returnerer svaret som ren tekst
    public static function generateResponse(string $prompt): ?string
    {
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ];

        $url = self::API_URL . self::MODEL . ':generateContent?key=' . GEMINI_API_KEY;

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
        ]);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || !$raw) {
            return null;
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return null;
        }

        // Trygt å hente ut tekst
        return $json['candidates'][0]['content']['parts'][0]['text']
            ?? null;
    }
}
