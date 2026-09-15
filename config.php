<?php
/**
 * config.php — Configuración global de la aplicación
 *
 * Define constantes de base de datos, correo y géneros.
 * Se carga en todos los puntos de entrada (api.php, vistas).
 */

// ── Secretos (BD de producción, Telegram, admin) ──────
// Viven en config.local.php, que NO se sube a git (ver .gitignore).
// Plantilla y explicación en config.local.example.php.
$secretsFile = __DIR__ . '/config.local.php';
if (!file_exists($secretsFile)) {
    http_response_code(500);
    die('Falta config.local.php. Copia config.local.example.php como config.local.php y rellena los valores.');
}
require_once $secretsFile;

// ── Base de datos ─────────────────────────────────────
// Se diferencia entre Windows (XAMPP local) y Linux (SiteGround producción)
if (PHP_OS_FAMILY === 'Windows') {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'hitster_musicos');
} else {
    define('DB_HOST', DB_HOST_PROD);
    define('DB_USER', DB_USER_PROD);
    define('DB_PASS', DB_PASS_PROD);
    define('DB_NAME', DB_NAME_PROD);
}

// ── Correo saliente ───────────────────────────────────
// Usa PHP mail() del servidor — no requiere credenciales SMTP externas.
// En local (Windows) el envío está desactivado para no depender de configuración extra.
define('SMTP_FROM',    'noreply@hitstoric.nite.black');
define('SMTP_FROM_NAME', 'Hitstoric');
define('SMTP_ENABLED', PHP_OS_FAMILY !== 'Windows');

// ── Géneros disponibles ───────────────────────────────
// Lista completa de géneros que el dinamizador puede elegir al crear partida.
// 'Todos' significa sin filtrar por género.
const GENRES = [
    'Todos',
    'Rock Internacional',
    'Pop/Rock Español',
    '80s',
    'New Age',
    'Rock en Español',
    'Trap/Rap Internacional',
    'Trap/Rap en Español',
    'Actualidad',
];

// ── URL base dinámica ─────────────────────────────────
// Calcula automáticamente la URL raíz del proyecto, tanto en XAMPP
// (donde puede estar en una subcarpeta) como en SiteGround (raíz del dominio).
// Por CLI (cron) no hay $_SERVER['HTTP_HOST']/DOCUMENT_ROOT, así que se fija a mano.
if (PHP_SAPI === 'cli') {
    define('BASE_URL', PHP_OS_FAMILY === 'Windows'
        ? 'http://localhost/Practicas/Web%20Musicos'
        : 'https://hitstoric.nite.black');
} else {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
    $basePath = str_replace($docRoot, '', $projRoot);
    define('BASE_URL', $scheme . '://' . $host . $basePath);
}
