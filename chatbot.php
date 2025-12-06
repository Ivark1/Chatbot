<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/ChatHistory.php';
require_once __DIR__ . '/lib/ChatModel.php';
require_once __DIR__ . '/Api/ApiHelper.php';

// Lokasjonsdeteksjon
function extract_place(string $text): ?string
{
    $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

    if (!preg_match_all('/\b(\p{Lu}[\p{L}]+)\b/u', $clean, $matches)) {
        return null;
    }

    $words = $matches[1];

    $forbidden = [
        'Hvordan','Hva','Hvor','Er','I','På','Det','Som','Du','Jeg','Han',
        'Hun','Vi','De','Når','Hvis','Har','Takk','Hei','Den','Og','Men','For'
    ];

    $candidates = array_values(array_filter(
        $words,
        static fn(string $w): bool => !in_array($w, $forbidden, true)
    ));

    return $candidates[count($candidates) - 1] ?? null;
}

// Antall dager for værmelding
function detect_forecast_days(string $text): int
{
    $lower = mb_strtolower($text, 'UTF-8');

    if (preg_match('/\b([1-7])\s*[- ]?dag(?:er|ers)?\b/u', $lower, $m)) {
        return (int)$m[1];
    }

    $wordNum = [
        'en'=>1,'ett'=>1,'to'=>2,'tre'=>3,'fire'=>4,
        'fem'=>5,'seks'=>6,'sju'=>7,'syv'=>7
    ];

    foreach ($wordNum as $w => $n) {
        if (preg_match('/\b'.$w.'\s+dag(?:er|ers)?\b/u', $lower)) {
            return $n;
        }
    }

    if (str_contains($lower, 'dag') && preg_match('/\b([1-7])\b/u', $lower, $m)) {
        return (int)$m[1];
    }

    return 0;
}

// Chathistorikk gir Gemini-llm kontekst fra 10 siste meldinger
function build_history_text(array $history): string
{
    if (!$history) return '';

    $slice = array_slice($history, -10);
    $lines = [];

    foreach ($slice as $msg) {
        $sender = $msg['sender'] ?? '';
        $text   = trim((string)($msg['message'] ?? ''));

        if ($text === '') continue;

        $lines[] = ($sender === 'user')
            ? "Bruker: {$text}"
            : "TravelBot: {$text}";
    }

    return $lines
        ? "Tidligere meldinger i samtalen:\n" . implode("\n", $lines)
        : '';
}

// POST-krav
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

$userMessage = trim($_POST['message'] ?? '');
if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Skriv en melding først']);
    exit;
}

if (mb_strlen($userMessage) > 2000) {
    http_response_code(413);
    echo json_encode(['error' => 'Meldingen er for lang (maks 2000 tegn).']);
    exit;
}

// Samtale-ID
$conversationId = $_POST['conversation_id'] ?? null;

if ($conversationId) {
    $conversationId = trim($conversationId);

    if (!preg_match('/^[0-9a-fA-F-]{1,64}$/', $conversationId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig conversation_id']);
        exit;
    }
} else {
    $c = ChatHistory::createConversation();
    if (!$c || empty($c['conversation_id'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Kunne ikke opprette ny samtale.']);
        exit;
    }
    $conversationId = $c['conversation_id'];
}

// Historikk
$history = ChatHistory::getHistory($conversationId);
ChatHistory::saveMessage($conversationId, 'user', $userMessage);

// Systemprompt
$systemPrompt =
"Du er TravelBot — en hjelpsom, presis og faktabasert norsk reiseassistent.
Svar alltid på norsk bokmål og bruk enkel markdown. Unngå kodeblokker.

Bruk alltid fakta som kommer fra API-data hvis de er tilgjengelige.";

// Meldingsanalyse
$isPureThanks = preg_match('/^\s*(takk|tusen takk|bra jobbet|👍)\s*$/iu', $userMessage);
$possibleCity = (!$isPureThanks && $userMessage !== '') ? extract_place($userMessage) : null;
$forecastDays = min(max(detect_forecast_days($userMessage), 0), 7);

// API-kontekst
$weatherContext    = '';
$forecastContext   = '';
$museumContext     = '';
$foodContext       = '';
$activitiesContext = '';

if ($possibleCity !== null) {

    $geo = ApiHelper::geocodeAddress($possibleCity);

    if ($geo && !empty($geo['lat']) && !empty($geo['lon'])) {

        $lat       = (float)$geo['lat'];
        $lon       = (float)$geo['lon'];
        $formatted = $geo['formatted'] ?? $possibleCity;

        // Vær
        try {
            $daysForApi = max(1, $forecastDays);
            $weather = ApiHelper::getWeather($lat, $lon, $daysForApi);

            if (!empty($weather['current'])) {
                $c       = $weather['current'];
                $temp    = $c['temperature_2m'] ?? null;
                $windKmh = $c['wind_speed_10m'] ?? null;
                $windMs  = $windKmh !== null ? round($windKmh / 3.6, 1) : null;
                $wcode   = $c['weather_code'] ?? null;

                $parts = [];
                if ($temp !== null)   $parts[] = "Temperatur nå: {$temp}°C";
                if ($windMs !== null) $parts[] = "Vind: {$windMs} m/s (≈ {$windKmh} km/t)";
                if ($wcode !== null)  $parts[] = "Værkode: {$wcode}";

                if ($parts) {
                    $weatherContext =
                        "\n\n(Værdata fra API for {$formatted}: " .
                        implode(', ', $parts) . ".)";
                }
            }

            if ($forecastDays > 0 && !empty($weather['daily'])) {
                $d = $weather['daily'];

                $times   = $d['time']               ?? [];
                $tMaxArr = $d['temperature_2m_max'] ?? [];
                $tMinArr = $d['temperature_2m_min'] ?? [];
                $precArr = $d['precipitation_sum']  ?? [];

                $count = min(count($times), 7, $forecastDays);

                if ($count > 0) {
                    $lines = [];
                    for ($i = 0; $i < $count; $i++) {
                        $lines[] =
                            "- {$times[$i]}: Max {$tMaxArr[$i]}°C, Min {$tMinArr[$i]}°C, Nedbør {$precArr[$i]} mm";
                    }

                    $forecastContext =
                        "\n\n(Forecast fra API for {$formatted} ({$count} dag(er)):\n" .
                        implode("\n", $lines) . "\n)";
                }
            }

        } catch (\Throwable $ignored) {}

        // Aktiviteter
        try {
            // Museum
            $museumData = array_merge(
                ApiHelper::getPlaces($lat, $lon, 'tourism.museum')['features'] ?? [],
                ApiHelper::getPlaces($lat, $lon, 'tourism.gallery')['features'] ?? []
            );

            $museumList = [];
            foreach (array_slice($museumData, 0, 10) as $feat) {
                $p = $feat['properties'] ?? [];
                $name = $p['name'] ?? null;
                if (!$name) continue;
                $addr = $p['address_line1'] ?? ($p['formatted'] ?? '');
                $museumList[] = $addr ? "{$name} — {$addr}" : $name;
            }
            if ($museumList) {
                $museumContext =
                    "\n\n(Museer i {$formatted}: " . implode('; ', $museumList) . ".)";
            }

            // Restauranter
            $foodData = array_merge(
                ApiHelper::getPlaces($lat, $lon, 'catering.restaurant')['features'] ?? [],
                ApiHelper::getPlaces($lat, $lon, 'catering.cafe')['features'] ?? []
            );

            $foodList = [];
            foreach (array_slice($foodData, 0, 10) as $feat) {
                $p = $feat['properties'] ?? [];
                $name = $p['name'] ?? null;
                if (!$name) continue;
                $addr = $p['address_line1'] ?? ($p['formatted'] ?? '');
                $foodList[] = $addr ? "{$name} — {$addr}" : $name;
            }
            if ($foodList) {
                $foodContext =
                    "\n\n(Restauranter og kafeer i {$formatted}: " . implode('; ', $foodList) . ".)";
            }

            // Attraksjoner/severdigheter
            $attractions = ApiHelper::getPlaces($lat, $lon, 'tourism.attraction')['features'] ?? [];

            $aList = [];
            foreach (array_slice($attractions, 0, 10) as $feat) {
                $p = $feat['properties'] ?? [];
                $name = $p['name'] ?? null;
                if (!$name) continue;
                $addr = $p['address_line1'] ?? ($p['formatted'] ?? '');
                $aList[] = $addr ? "{$name} — {$addr}" : $name;
            }
            if ($aList) {
                $activitiesContext =
                    "\n\n(Attraksjoner i {$formatted}: " . implode('; ', $aList) . ".)";
            }

        } catch (\Throwable $ignored) {}
    }
}

// Prompt
$historyText = build_history_text($history);

$contextBlock = '';
if ($weatherContext || $forecastContext || $museumContext || $foodContext || $activitiesContext) {
    $contextBlock =
        "\n\nTilgjengelige fakta fra API (skal brukes i svaret):" .
        $weatherContext .
        $forecastContext .
        $museumContext .
        $foodContext .
        $activitiesContext;
}

$finalPrompt =
    $systemPrompt .
    "\n\n----------------------\n\n" .
    $historyText .
    "\n\n----------------------\n\n" .
    "Ny melding fra brukeren:\n\"{$userMessage}\"" .
    $contextBlock .
    "\n\nSkriv ett samlet, tydelig og hjelpsomt svar.";

// Genererer svar
$responseText = ChatModel::generateResponse($finalPrompt);

if (!$responseText || trim($responseText) === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Ingen respons fra modellen.']);
    exit;
}

ChatHistory::saveMessage($conversationId, 'assistant', $responseText);

echo json_encode([
    'conversation_id' => $conversationId,
    'message'         => $responseText
], JSON_UNESCAPED_UNICODE);
