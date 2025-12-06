<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Helpers.php';

class ChatHistory
{
    // Henter alle meldinger for en conversation_id, sortert etter created_at
    public static function getHistory(string $conversationId): array
    {
        $rows = Database::select(
            $_ENV['SUPABASE_TABLE'],
            ['conversation_id' => $conversationId],
            '*'
        );

        if (!$rows) {
            return [];
        }

        usort($rows, static function ($a, $b) {
            return strtotime($a['created_at'] ?? '') <=> strtotime($b['created_at'] ?? '');
        });

        return $rows;
    }

    // Lagrer en melding i databasen
    public static function saveMessage(string $conversationId, string $sender, string $message): bool
    {
        $data = [
            'conversation_id' => $conversationId,
            'sender'          => $sender,
            'message'         => $message,
            'created_at'      => date('c')
        ];

        $insert = Database::insert($_ENV['SUPABASE_TABLE'], $data);
        return $insert !== null;
    }

    // Oppretter en ny samtale med gyldig UUID
    public static function createConversation(): ?array
    {
        $uuid = Helpers::generateUuid();

        $insert = Database::insert($_ENV['SUPABASE_TABLE'], [
            'conversation_id' => $uuid,
            'sender'          => 'system',
            'message'         => 'Conversation started',
            'created_at'      => date('c')
        ]);

        return $insert[0] ?? null;
    }

    // Sletter en samtale og alle meldinger med samme conversation_id
    public static function deleteConversation(string $conversationId): bool
    {
        return Database::delete(
            $_ENV['SUPABASE_TABLE'],
            ['conversation_id' => $conversationId]
        );
    }
}
