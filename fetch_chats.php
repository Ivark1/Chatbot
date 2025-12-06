<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/ChatHistory.php';
require_once __DIR__ . '/lib/Helpers.php';
require_once __DIR__ . '/lib/Database.php';

// Liste over alle chats
if (isset($_GET['list'])) {

    $rows = Database::select($_ENV['SUPABASE_TABLE'], []);

    echo json_encode($rows ?? [], JSON_UNESCAPED_UNICODE);
    exit;
}

// Hent meldinger for en spesifikk samtale
$conversationId = $_GET['conversation_id'] ?? '';

if (
    !$conversationId ||
    !preg_match('/^[0-9a-fA-F-]{1,64}$/', $conversationId)
) {
    http_response_code(400);
    echo json_encode(['error' => 'Ugyldig conversation_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$history = ChatHistory::getHistory($conversationId);

echo json_encode($history ?? [], JSON_UNESCAPED_UNICODE);
