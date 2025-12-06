# TravelBot — Intelligent reiseassistent

TravelBot er en samtalebasert chatbot byggt for å hjelpe brukere med å planlegge reiser. Systemet kombinerer sanntidsdata fra flere API-er med Google Gemini 2.5 Flash for å levere personlige reisetips basert på vær, lokale attraksjoner, restauranter og museer.

## Funksjonalitet

- Naturlig samtalegrensesnitt på norsk bokmål
- Sanntids værdata og værvarsel (opptil 7 dager)
- Lokale attraksjoner, restauranter og museer basert på stedssøk
- Persistent samtalehistorikk lagret i Supabase
- PDF-eksport av samtaler
- Responsivt web-grensesnitt med moderne design

## Teknologi

**Frontend**
- HTML5, CSS3 (med CSS-variabler og gradienter)
- Vanilla JavaScript (ES6+)

**Backend**
- PHP 8+ (strict types, moderne syntaks)
- Composer for avhengighetsadministrasjon

**LLM**
- Google Gemini 2.5 Flash API

**Databaser**
- Supabase (PostgreSQL via REST API)

**Eksterne API-er**
- Open-Meteo (værdata og værvarsler)
- Geoapify (geokoding og stedssøk)

**PDF-generering**
- TCPDF

## Arkitektur

### Backend-struktur

```
├── Api/
│   └── ApiHelper.php          # API-integrasjoner (vær, geokoding, stedssøk)
├── config/
│   ├── config.php             # Miljøvariabler og konfigurasjon
│   └── .env                   # Miljøvariabler (ikke commit til Git)
├── lib/
│   ├── ChatHistory.php        # Samtalehistorikk-håndtering
│   ├── ChatModel.php          # Gemini API-integrasjon
│   ├── Database.php           # Supabase REST API wrapper
│   └── Helpers.php            # Hjelpefunksjoner (UUID, sanitering)
├── vendor/                    # Composer-avhengigheter
├── chatbot.php                # Hovedendepunkt for chatmeldinger
├── fetch_chats.php            # Henter samtalehistorikk
├── download_chat_pdf.php      # Genererer PDF av samtaler
├── index.php                  # Hovedside
├── script.js                  # Frontend-logikk
├── style.css                  # Styling
└── composer.json              # PHP-avhengigheter
```

### Dataflyt

1. Bruker sender melding via web-grensesnittet
2. `script.js` sender POST-forespørsel til `chatbot.php`
3. Backend:
   - Validerer input og henter samtalehistorikk
   - Ekstraherer stedsnavn fra brukermelding
   - Henter relevante API-data (vær, attraksjoner, restauranter)
   - Bygger prompt med historikk og API-kontekst
   - Sender til Gemini API via `ChatModel::generateResponse()`
   - Lagrer melding og svar i Supabase
4. Svar returneres som JSON og vises i chat-grensesnittet

## Installasjon

### Systemkrav

- PHP 8.0 eller nyere
- Composer
- Supabase-prosjekt
- API-nøkler:
  - Google Gemini API
  - Geoapify API
  - Supabase URL og API-nøkkel

### Oppsett

1. Klon repository:
```bash
git clone <repository-url>
cd travelbot
```

2. Installer PHP-avhengigheter:
```bash
composer install
```

3. Opprett `config/config.php` med følgende struktur:
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY']);
define('GEOAPIFY_API_KEY', $_ENV['GEOAPIFY_API_KEY']);
define('SUPABASE_URL', $_ENV['SUPABASE_URL']);
define('SUPABASE_KEY', $_ENV['SUPABASE_KEY']);
```

4. Opprett `config/.env`-fil:
```
GEMINI_API_KEY=din_gemini_api_nøkkel
GEOAPIFY_API_KEY=din_geoapify_api_nøkkel
SUPABASE_URL=din_supabase_url
SUPABASE_KEY=din_supabase_anon_nøkkel
SUPABASE_TABLE=chat_messages
```

5. Opprett Supabase-tabell:
```sql
CREATE TABLE chat_messages (
    id SERIAL PRIMARY KEY,
    conversation_id TEXT NOT NULL,
    sender TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_conversation_id ON chat_messages(conversation_id);
CREATE INDEX idx_created_at ON chat_messages(created_at);
```

6. Start lokal webserver:
```bash
php -S localhost:8000
```

7. Åpne `http://localhost:8000` i nettleseren

## Bruksanvisning

### For brukere

**Viktig:** 
- Bruk alltid stor forbokstav på stedsnavn (Oslo, Bergen, Paris) for best resultat
- Jo mer spesifikk du er i spørsmålet ditt, jo bedre svar får du
- TravelBot fungerer best for å sjekke større områder som byer

**Eksempler på gode spørsmål:**

- "Jeg skal reise til Oslo i dag. Hva anbefaler du å gjøre?"
- "Hvordan er været i Bergen de neste 3 dagene?"
- "Kan du foreslå restauranter i Trondheim?"
- "Jeg skal til Paris i helgen. Hva bør jeg se?"
- "Hva er de beste museene i Stockholm?"
- "Jeg skal til Berlin, kan du si litt om været og aktiviteter man kan gjøre der?"

**Funksjonalitet:**

- Værvarsler: Spesifiser antall dager (f.eks. "været i 5 dager")
- Attraksjoner: Spør om museer, restauranter eller aktiviteter
- Samtalehistorikk: Alle samtaler lagres automatisk
- PDF-eksport: Klikk "Last ned chatlog som PDF" for å eksportere aktiv samtale

### For utviklere

**API-integrasjon**

`ApiHelper.php` tilbyr følgende metoder:

```php
// Geokoding
$geo = ApiHelper::geocodeAddress('Oslo');
// Returns: ['lat' => 59.9, 'lon' => 10.7, 'formatted' => 'Oslo, Norway']

// Værdata
$weather = ApiHelper::getWeather($lat, $lon, $forecastDays);

// Stedssøk
$places = ApiHelper::getPlaces($lat, $lon, 'tourism.museum');
```

**Databaseoperasjoner**

```php
// SELECT
$rows = Database::select('chat_messages', ['conversation_id' => $id]);

// INSERT
$result = Database::insert('chat_messages', $data);

// UPDATE
Database::update('chat_messages', ['id' => $id], $updates);

// DELETE
Database::delete('chat_messages', ['conversation_id' => $id]);
```

**Samtalehistorikk**

```php
// Opprett ny samtale
$conversation = ChatHistory::createConversation();

// Lagre melding
ChatHistory::saveMessage($conversationId, 'user', $message);

// Hent historikk
$history = ChatHistory::getHistory($conversationId);
```

## Sikkerhet

Implementerte sikkerhetstiltak:

- Strict input-validering (regex for conversation_id, lengdebegrensning på meldinger)
- Prepared statements via Supabase REST API
- Content Security Policy headers
- XSS-beskyttelse via `htmlspecialchars()`
- CSRF-beskyttelse via same-origin policy
- Rate limiting på API-kall (10s timeout)
- Sanitering av brukerinput før lagring og visning

## Konfigurasjon

### Gemini-modell

Standard modell er `gemini-2.5-flash`. For å endre:

```php
// I ChatModel.php
private const MODEL = 'gemini-2.5-flash'; // eller annen Gemini-modell
```

### API-timeouts

```php
// I ApiHelper.php og Database.php
CURLOPT_TIMEOUT => 10 // sekunder
```

### Maksimumsgrenser

- Meldingslengde: 2000 tegn
- Værvarseldager: 1-7 dager
- Stedssøk-resultater: 20 per kategori
- Chat-historikk i UI: 10 siste meldinger i prompt

## Feilsøking

**Problem:** "Ingen respons fra modellen"
- Sjekk at `GEMINI_API_KEY` er gyldig
- Kontroller nettverkstilkobling
- Se PHP error log for detaljer

**Problem:** "Kunne ikke laste samtale"
- Verifiser Supabase-konfigurasjon
- Sjekk at tabellen `chat_messages` eksisterer
- Kontroller at `SUPABASE_KEY` har nødvendige rettigheter

**Problem:** Stedsnavn gjenkjennes ikke
- Bruk stor forbokstav på stedsnavn
- Vær spesifikk (f.eks. "Oslo, Norge" fremfor bare "Oslo")
- Sjekk at Geoapify API-nøkkel er gyldig