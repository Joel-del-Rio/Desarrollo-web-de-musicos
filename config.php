<?php
/**
 * config.php — Configuración global de la aplicación
 *
 * Define constantes de base de datos, correo y géneros.
 * Se carga en todos los puntos de entrada (api.php, vistas).
 */

// ── Base de datos ─────────────────────────────────────
// Se diferencia entre Windows (XAMPP local) y Linux (SiteGround producción)
if (PHP_OS_FAMILY === 'Windows') {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'hitster_musicos');
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'ug5qzildxb4vc');
    define('DB_PASS', '0lx5wdgggcri');
    define('DB_NAME', 'dbe7oc67cjh788');
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
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$projRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
$basePath = str_replace($docRoot, '', $projRoot);
define('BASE_URL', $scheme . '://' . $host . $basePath);
