<?php
/**
 * config.local.example.php — Plantilla de secretos
 *
 * Copia este archivo como "config.local.php" (en la misma carpeta) y rellena
 * los valores reales. Ese archivo NUNCA se sube a git (está en .gitignore),
 * así que cada entorno (tu PC y el servidor) tiene su propia copia.
 *
 * En Windows (XAMPP local) no hace falta rellenar nada: la BD local no
 * tiene contraseña y basta con dejar el admin/telegram vacíos para probar.
 */

// ── Base de datos en producción (SiteGround) ──────────────────────────
define('DB_HOST_PROD', 'localhost');
define('DB_USER_PROD', '');
define('DB_PASS_PROD', '');
define('DB_NAME_PROD', '');

// ── Bot de Telegram (anuncio de partidas públicas) ────────────────────
// Crea el bot con @BotFather, añádelo al grupo/canal y pon aquí el token
// y el chat_id. Con TELEGRAM_ENABLED en false no se anuncia nada.
define('TELEGRAM_ENABLED', false);
define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_CHAT_ID', '');

// ── Webhook de Discord (anuncio de partidas públicas) ─────────────────
// Canal de Discord → icono ⚙️ → Integraciones → Webhooks → Nuevo Webhook →
// Copiar URL. Con DISCORD_ENABLED en false no se anuncia nada.
define('DISCORD_ENABLED', false);
define('DISCORD_WEBHOOK_URL', '');

// ── Acceso de administrador (superadmin y panel de premios) ───────────
// Genera el hash de tu contraseña con:
//   php -r "echo password_hash('tu_contraseña_nueva', PASSWORD_BCRYPT), PHP_EOL;"
define('ADMIN_EMAIL', 'joel@nite.black');
define('ADMIN_PASS_HASH', '');
