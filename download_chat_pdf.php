<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/ChatHistory.php';
require_once __DIR__ . '/lib/Helpers.php';
require_once __DIR__ . '/vendor/autoload.php';

// Inputvalidering
$conversationId = $_GET['conversation_id'] ?? '';

if (
    !$conversationId ||
    !preg_match('/^[0-9a-fA-F-]{1,64}$/', $conversationId)
) {
    http_response_code(400);
    echo "Ugyldig eller manglende conversation_id.";
    exit;
}

// Hent meldinger
$messages = ChatHistory::getHistory($conversationId);

if (empty($messages)) {
    http_response_code(404);
    echo "Ingen meldinger funnet for denne samtalen.";
    exit;
}

// Markdown → PDF HTML
function format_markdown_for_pdf(string $text): string
{
    // Beskytt HTML
    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Enkel markdown-støtte
    $safe = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $safe);

    // Linjeskift
    return nl2br($safe);
}

// Initialiser PDF
if (!class_exists('TCPDF')) {
    http_response_code(500);
    echo "PDF-modul mangler (TCPDF ikke lastet).";
    exit;
}

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetCreator('TravelBot');
$pdf->SetAuthor('TravelBot');
$pdf->AddPage();

// Overskrift
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 8, 'TravelBot - Chathistorikk', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(
    0,
    5,
    date('d.m.Y H:i') . " | Chat-ID: " . substr($conversationId, 0, 8),
    0,
    1,
    'L'
);
$pdf->Ln(5);

// Meldinger
foreach ($messages as $msg) {

    $sender    = ($msg['sender'] === 'user') ? 'Du' : 'TravelBot';
    $timestamp = date('H:i', strtotime($msg['created_at'] ?? 'now'));
    $align     = ($msg['sender'] === 'user') ? 'R' : 'L';

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 5, "{$sender} ({$timestamp})", 0, 1, $align);

    $pdf->SetFont('helvetica', '', 9);
    $html = format_markdown_for_pdf((string)$msg['message']);

    $pdf->writeHTMLCell(
        0,
        0,
        '',
        '',
        $html,
        0,
        1,
        false,
        true,
        $align,
        true
    );

    $pdf->Ln(3);
}

// Send PDF
$filename = 'TravelBot_Chat_' . date('Ymd_His') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$pdf->Output($filename, 'D');
