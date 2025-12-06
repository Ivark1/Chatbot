<?php
declare(strict_types=1);

class Helpers
{
    // Renser input fra bruker
    public static function cleanInput(string $text): string
    {
        return trim(strip_tags($text));
    }

    // Lager en UUIDv4
    public static function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    // Formaterer chat-historikk til tekst som brukes for prompt
    public static function formatHistory(array $history): string
    {
        $formatted = "";

        foreach ($history as $entry) {
            $sender = ucfirst($entry['sender'] ?? 'User');
            $msg    = $entry['message'] ?? '';
            $formatted .= "{$sender}: {$msg}\n";
        }

        return $formatted;
    }

    // Setter sammen systemmelding, historikk og brukermelding til en prompt
    public static function buildPrompt(array $history, string $newMessage): string
    {
        $system = "Du er en hjelpsom assistent. Svar tydelig og vennlig.";
        $formattedHistory = self::formatHistory($history);

        return "{$system}\n\n"
            . "Samtalehistorikk:\n{$formattedHistory}\n"
            . "Bruker: {$newMessage}\n"
            . "Assistent:";
    }

    // Trygg JSON-dekoding med fallback til tom array
    public static function safeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
