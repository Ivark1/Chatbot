<?php
// config/config.php
declare(strict_types=1);

$root = dirname(__DIR__); // peker på ChatbotGruppe17/

// Laster Composer-autoload
require_once $root . '/vendor/autoload.php';

use Dotenv\Dotenv;

/* ----------------------------------------------------
 * LAST .env
 * ---------------------------------------------------- */
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

/* ----------------------------------------------------
 * KRITISKE VARIABLER SOM M� FINNES
 * ---------------------------------------------------- */
$required = ['SUPABASE_URL', 'SUPABASE_KEY', 'SUPABASE_TABLE'];

foreach ($required as $key) {
    if (empty($_ENV[$key])) {
        throw new RuntimeException("Mangler milj�variabel: {$key}");
    }
}

/* ----------------------------------------------------
 * DEFIN�R KONSTANTER (TRYGT)
 * ---------------------------------------------------- */
define('SUPABASE_URL',   rtrim($_ENV['SUPABASE_URL'], '/'));
define('SUPABASE_KEY',   $_ENV['SUPABASE_KEY']);
define('SUPABASE_TABLE', $_ENV['SUPABASE_TABLE']);

define('GEMINI_API_KEY',   $_ENV['GEMINI_API_KEY']   ?? '');
define('GEOAPIFY_API_KEY', $_ENV['GEOAPIFY_API_KEY'] ?? '');

/* ----------------------------------------------------
 * INGEN LOGGING AV N�KLER I PRODUKSJON
 * ---------------------------------------------------- */
// Ikke logg API-n�kler. Ikke send til output. Ikke skriv til errors.
