<?php
/**
 * Hitstoric — Front Controller
 * Punto de entrada único. Gestiona el routing básico
 * mediante ?page= (vistas) y ?action= (API).
 */
require_once __DIR__ . '/config.php';

// ── Páginas disponibles ───────────────────────────────
const PAGES = [
    'home'    => __DIR__ . '/Vista/index.php',
    'admin'   => __DIR__ . '/Vista/admin.php',
    'player'  => __DIR__ . '/Vista/player.php',
    'songs'   => __DIR__ . '/Vista/songs.php',
    'premios' => __DIR__ . '/Vista/premios.php',
];

// ── Si lleva ?action= es una llamada a la API ─────────
if (isset($_GET['action']) || isset($_POST['action'])) {
    require __DIR__ . '/Controlador/api.php';
    exit;
}

// ── Resolver página ───────────────────────────────────
$page = strtolower(trim($_GET['page'] ?? 'home'));

// Alias por si llega la URL limpia con extensión o variante
$aliases = [
    ''        => 'home',
    'index'   => 'home',
    'api'     => '_api',   // ruta /api → api.php directamente
];
$page = $aliases[$page] ?? $page;

// ── Ruta /api sin action → responde 400 ──────────────
if ($page === '_api') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro action']);
    exit;
}

// ── Página no encontrada → home ───────────────────────
if (!array_key_exists($page, PAGES)) {
    http_response_code(404);
    $page = 'home';
}

// ── Cargar vista ──────────────────────────────────────
require PAGES[$page];
