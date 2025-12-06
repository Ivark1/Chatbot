<?php
declare(strict_types=1);

// Eksempelspørsmål som knapper
$exampleQuestions = [
    [
        "q" => "Jeg skal reise til Oslo i dag. Hva anbefaler du å gjøre?",
        "a" => "Basert på værmeldingen for Oslo i dag, er det en flott dag for å være ute! Det er en solfyllt og tørr dag, med en temperatur på mellom **7°C og 13°C**.
        
        Her er noen forslag til aktiviteter ute:
        * **Vigelandsparken**: Parken inneholder over 200 skulpturer i bronse, granitt og smijern og Gustav Vigeland er parkens skaper.
        
        * **Akershus Festning**: En flott og historisk severdig som ligger midt i Oslo sentrum. Den består av et middelalderlig slott og en festning fra renessansen.
        
        Jeg håper disse reisetipsene gir deg en flott dag!"
    ],
    [
        "q" => "Hva med aktiviteter innendørs i Oslo?",
        "a" => "Selvfølgelig, la oss se på Oslo!
                
                Det blir en kjølig dag i Oslo, med temperaturer som ligger mellom **4.5°C og 9.1°C**. Det er også ventet litt nedbør, med **7.5mm regn** i løpet av dagen. Husk å kle deg godt og ta med paraply eller regntøy om du skal ut!
                
                Med kjølige temperaturer og litt nedbør, er innendørs aktiviteter et utmerket valg i Oslo i dag. Her er noen forslag basert på informasjonen jeg har:
                
                * **Nasjonalgalleriet**: Dette er et fantastisk sted for kunstelskere, med en imponerende samling av norsk og internasjonal kunst fra antikken til midten av det 20. århundre. Her finner du blant annet Edvard Munchs Skrik. Perfekt for en inspirerende dag innendørs!
                
                * **Oslo rådhus**: Rådhuset er ikke bare et administrativt bygg, men også en arkitektonisk perle fylt med kunst og historie. Du kan ofte gå inn og beundre den store hallen og de imponerende veggmaleriene, som gir et unikt innblikk i norsk kultur og samfunn.
                                
                Håper disse forslagene hjelper deg med å planlegge en fin dag innendørs i Oslo! Si gjerne ifra hvis du har andre spørsmål."
    ],
];

// Hjelpefunksjoner
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Sikkerhetsheadere samlet i én funksjon
function sendSecurityHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'; upgrade-insecure-requests");
}

sendSecurityHeaders();

?>
<!DOCTYPE html>
<html lang="no">
<head>
  <meta charset="UTF-8">
  <title>TravelBot</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <h3><a href="#" id="logo-link">✈️ TravelBot</a></h3>
    <nav>
      <ul id="chat-history">
        <li><a href="#" id="home-btn">Chat</a></li>
      </ul>
    </nav>
  </aside>

  <!-- Chat -->
  <div class="chat-container" role="main">

    <section class="example-section">
      <h2 class="example-heading">
        For å begynne å bruke TravelBot, kan du prøve disse spørsmålene.
        <br>Videre kan du skrive egne spørsmål.
        <br><br>Husk å bruke stor bokstav på stedsnavn!
      </h2>

      <div class="cards">
        <?php foreach ($exampleQuestions as $item): ?>
          <button
            class="example-btn"
            type="button"
            data-q="<?= h($item['q']) ?>"
            data-a="<?= h($item['a']) ?>"
          >
            <?= h($item['q']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </section>

    <h2>TravelBot</h2>

    <!-- Chat-logg fylles av script.js -->
    <div id="chat" class="chat-log"></div>

    <div id="input-area">
      <input type="text" id="message" placeholder="Skriv en melding...">
      <button id="send-btn" type="button">Send</button>
    </div>
  </div>

  <!-- Knapper for settings og pdf-download -->
  <button id="download-pdf-btn" class="topbar-btn download-btn custom-btn btn-1" type="button">
      Last ned chatlog som PDF
  </button>

  <script src="script.js" defer></script>
</body>
</html>
