<?php
// ── Credenciales de base de datos ─────────────────────
if (strpos(__DIR__, '/home/u72') !== false) {
    // SiteGround
    define('DB_HOST', 'localhost');
    define('DB_USER', 'ug5qzildxb4vc');
    define('DB_PASS', '0lx5wdgggcri');
    define('DB_NAME', 'dbe7oc67cjh788');
} else {
    // XAMPP local
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'hitster_musicos');
}

// ── Géneros disponibles ───────────────────────────────
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

// ── BASE_URL dinámica (XAMPP subfolder y SiteGround root) ──
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$projRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
$basePath = str_replace($docRoot, '', $projRoot);
define('BASE_URL', $scheme . '://' . $host . $basePath);
