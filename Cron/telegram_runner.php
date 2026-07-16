<?php
/**
 * telegram_runner.php — Partidas automáticas anunciadas por Telegram
 *
 * Pensado para ejecutarse por cron cada minuto. En cada ejecución:
 *  1. Si es una hora en punto (según TELEGRAM_INTERVAL_MINUTES, hora española) y no
 *     hay ninguna partida en curso, crea una partida nueva (con votación de género)
 *     y anuncia el PIN en Telegram.
 *  2. Mientras haya una partida en curso, comprueba cada pocos segundos (no solo una
 *     vez) si toca arrancarla, revelar resultados o avanzar de ronda, durante un
 *     margen de ~50s dentro de la misma ejecución. Así TELEGRAM_REVEAL_SECONDS y
 *     TELEGRAM_WAIT_SECONDS se cumplen con precisión de segundos en vez de quedar
 *     atados a la granularidad de 1 minuto del cron.
 *
 * El bucle está acotado a ~50s (deja margen antes de que el cron dispare el
 * siguiente minuto) y con sleep corto, así que nunca hay riesgo de que el
 * hosting mate el proceso por timeout ni de que se solape con la siguiente
 * ejecución (además del flock de más abajo).
 *
 * Cron sugerido (cada minuto):
 *   * * * * * php /ruta/al/proyecto/Cron/telegram_runner.php >> /ruta/al/proyecto/Cron/telegram_runner.log 2>&1
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Modelo/Database.php';
require_once __DIR__ . '/../Modelo/Game.php';
require_once __DIR__ . '/../Modelo/Player.php';
require_once __DIR__ . '/../Modelo/TelegramBot.php';

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por CLI'); }
if (!TELEGRAM_ENABLED) exit("Telegram deshabilitado (TELEGRAM_ENABLED=false)\n");

// Fija la zona horaria explícitamente para que "en punto" sea la hora española
// sin depender del default del hosting (que suele venir en UTC)
date_default_timezone_set('Europe/Madrid');

// Evita que dos ejecuciones se solapen si una tarda más de lo normal
$lockFile = sys_get_temp_dir() . '/hitstoric_telegram_runner.lock';
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit("Ya hay otra ejecución en curso, se aborta.\n");

function log_line(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . "] $msg\n";
}

/** Procesa un paso de la partida activa (arrancar / revelar / avanzar ronda). */
function process_active_game(PDO $db, Game $game, Player $player, TelegramBot $bot, array $active): void {
    $gameId    = (int)$active['game_id'];
    $phase     = $active['phase'];
    $changedAt = strtotime($active['phase_changed_at'] . ' UTC');
    $elapsed   = time() - $changedAt;

    switch ($phase) {

        case 'waiting':
            if ($elapsed < TELEGRAM_WAIT_SECONDS) {
                log_line("Partida #$gameId en espera (" . (TELEGRAM_WAIT_SECONDS - $elapsed) . "s restantes).");
                return;
            }

            $players = $player->getByGame($gameId);
            if (count($players) === 0) {
                $db->prepare("UPDATE telegram_runs SET phase='finished' WHERE game_id=?")->execute([$gameId]);
                $bot->sendMessage("😴 Nadie se unió a tiempo, cancelo la partida.");
                log_line("Partida #$gameId cancelada: sin jugadores.");
                return;
            }

            $game->start($gameId);
            $winningGenre = $game->getById($gameId)['selected_genre'] ?? 'Todos';
            $db->prepare(
                "UPDATE telegram_runs SET phase='question', phase_changed_at=UTC_TIMESTAMP() WHERE game_id=?"
            )->execute([$gameId]);
            $bot->sendMessage(
                "🚀 ¡Empieza la partida con " . count($players) . " jugador(es)!\n" .
                "🎼 Género ganador: *{$winningGenre}*\n" .
                "Ronda 1 de " . TELEGRAM_ROUNDS . "."
            );
            log_line("Partida #$gameId arrancada con " . count($players) . " jugadores, género '$winningGenre'.");
            return;

        case 'question':
            $state = $game->getState($gameId);
            if ($state['status'] === 'question' && $state['time_left'] > 0) {
                log_line("Partida #$gameId en pregunta (" . $state['time_left'] . "s restantes).");
                return;
            }

            // El tiempo se acabó: revelar resultados si el propio getState() no lo hizo ya.
            // No se anuncia nada aquí — el resumen completo se manda al terminar la partida.
            if ($state['status'] === 'question') $game->showResults($gameId);

            $db->prepare(
                "UPDATE telegram_runs SET phase='results', phase_changed_at=UTC_TIMESTAMP() WHERE game_id=?"
            )->execute([$gameId]);
            log_line("Partida #$gameId: ronda {$state['current_round']} revelada.");
            return;

        case 'results':
            if ($elapsed < TELEGRAM_REVEAL_SECONDS) {
                log_line("Partida #$gameId mostrando resultados (" . (TELEGRAM_REVEAL_SECONDS - $elapsed) . "s restantes).");
                return;
            }

            $newStatus = $game->nextRound($gameId);

            if ($newStatus === 'finished') {
                $songs = $db->prepare(
                    "SELECT s.title, s.artist, s.year
                     FROM game_songs gs JOIN songs s ON s.id = gs.song_id
                     WHERE gs.game_id = ? ORDER BY gs.round_number ASC"
                );
                $songs->execute([$gameId]);

                $lines = ['🏁 *¡Partida terminada! Resumen:*', ''];
                foreach ($songs->fetchAll() as $i => $s) {
                    $lines[] = ($i + 1) . ". {$s['title']} — {$s['artist']} ({$s['year']})";
                }

                $leaderboard = $player->getByGame($gameId);
                $lines[] = '';
                $lines[] = '🏆 *Podio:*';
                foreach (array_slice($leaderboard, 0, 5) as $i => $p) {
                    $medal = ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) . '.';
                    $lines[] = "{$medal} {$p['name']} — {$p['score']} pts";
                }
                $bot->sendMessage(implode("\n", $lines));
                $db->prepare("UPDATE telegram_runs SET phase='finished' WHERE game_id=?")->execute([$gameId]);
                log_line("Partida #$gameId finalizada y resumen anunciado.");
            } else {
                $db->prepare(
                    "UPDATE telegram_runs SET phase='question', phase_changed_at=UTC_TIMESTAMP() WHERE game_id=?"
                )->execute([$gameId]);
                log_line("Partida #$gameId: siguiente ronda.");
            }
            return;
    }
}

$db     = Database::getInstance()->pdo();
$game   = new Game();
$player = new Player();
$bot    = new TelegramBot(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID);

// ── ¿Hay alguna partida de Telegram activa (no finalizada)? ──
$active = $db->query(
    "SELECT * FROM telegram_runs WHERE phase != 'finished' ORDER BY id DESC LIMIT 1"
)->fetch();

if (!$active) {
    // No hay ninguna en curso: ¿es la hora en punto que toca?
    // TELEGRAM_INTERVAL_MINUTES se interpreta en horas completas (60 = cada hora,
    // 120 = cada 2 horas en las horas pares, etc.), siempre alineado a :00.
    $hoursInterval = max(1, (int)round(TELEGRAM_INTERVAL_MINUTES / 60));
    $isHourMark    = (int)date('i') === 0 && (int)date('H') % $hoursInterval === 0;

    if (!$isHourMark) {
        log_line('No es la hora en punto que toca (ahora ' . date('H:i') . ').');
        flock($lock, LOCK_UN);
        exit;
    }

    $result = $game->create(
        TELEGRAM_ROUNDS, TELEGRAM_QUESTION_TIME, 'Todos',
        1, 1, 1, 'shared', '', 0, '', '', '', [], 0, 'song',
        true // genreVote: el género se decide por votación de los jugadores al arrancar
    );
    $gameId = (int)$result['id'];

    $db->prepare(
        "INSERT INTO telegram_runs (game_id, phase, phase_changed_at, created_at)
         VALUES (?, 'waiting', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
    )->execute([$gameId]);

    $joinUrl = BASE_URL . '/player?pin=' . $result['pin'];
    $waitMin = (int)round(TELEGRAM_WAIT_SECONDS / 60);
    $bot->sendMessage(
        "🎮 *¡Nueva partida de Hitstoric!*\n" .
        "PIN: `{$result['pin']}`\n" .
        "Únete aquí: {$joinUrl}\n" .
        "🗳️ Al entrar podéis votar el género — gana el más votado.\n" .
        "⏳ Empieza en {$waitMin} minutos, ¡daos prisa!"
    );
    log_line("Partida #$gameId creada, PIN {$result['pin']}, anunciada en Telegram.");
    flock($lock, LOCK_UN);
    exit;
}

// ── Hay una partida en curso: reprocesar cada pocos segundos durante ~50s ──
// para que las transiciones no queden atadas a la granularidad de 1 min del cron.
$deadline = time() + 50;
while (true) {
    process_active_game($db, $game, $player, $bot, $active);

    if (time() >= $deadline) break;
    sleep(2);

    $active = $db->query(
        "SELECT * FROM telegram_runs WHERE phase != 'finished' ORDER BY id DESC LIMIT 1"
    )->fetch();
    if (!$active) break; // la partida terminó o se canceló: nada más que hacer en este tick
}

flock($lock, LOCK_UN);
