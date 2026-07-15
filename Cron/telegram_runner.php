<?php
/**
 * telegram_runner.php — Partidas automáticas anunciadas por Telegram
 *
 * Pensado para ejecutarse por cron cada minuto. En cada ejecución:
 *  1. Si toca (según TELEGRAM_INTERVAL_MINUTES) y no hay ninguna partida en curso,
 *     crea una partida nueva (con votación de género) y anuncia el PIN en Telegram.
 *  2. Si hay una partida en la sala de espera y ya pasaron TELEGRAM_WAIT_SECONDS,
 *     la arranca (cierra la votación y elige el género más votado), o la cancela
 *     si no se unió nadie.
 *  3. Si hay una partida en pregunta y se acabó el tiempo, revela el resultado
 *     (sin anunciarlo — el resumen completo se manda solo al final).
 *  4. Si hay una partida en resultados y ya pasó TELEGRAM_REVEAL_SECONDS, avanza
 *     de ronda (o cierra la partida y anuncia el resumen + podio si era la última).
 *
 * No hace sleep en ningún punto — cada tick del cron hace como mucho un paso,
 * así que una ejecución nunca tarda más de una fracción de segundo y no hay
 * riesgo de que el hosting mate el proceso por timeout.
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

// Evita que dos ejecuciones se solapen si una tarda más de lo normal
$lockFile = sys_get_temp_dir() . '/hitstoric_telegram_runner.lock';
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit("Ya hay otra ejecución en curso, se aborta.\n");

function log_line(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . "] $msg\n";
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
    // No hay ninguna en curso: ¿toca crear una nueva?
    $lastCreatedAt = $db->query("SELECT MAX(created_at) FROM telegram_runs")->fetchColumn();
    $secondsSinceLast = $lastCreatedAt ? (time() - strtotime($lastCreatedAt . ' UTC')) : PHP_INT_MAX;

    if ($secondsSinceLast < TELEGRAM_INTERVAL_MINUTES * 60) {
        log_line("Aún no toca crear partida (faltan " . (TELEGRAM_INTERVAL_MINUTES * 60 - $secondsSinceLast) . "s).");
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

// ── Hay una partida en curso: procesar según su fase ──
$gameId    = (int)$active['game_id'];
$phase     = $active['phase'];
$changedAt = strtotime($active['phase_changed_at'] . ' UTC');
$elapsed   = time() - $changedAt;

switch ($phase) {

    case 'waiting':
        if ($elapsed < TELEGRAM_WAIT_SECONDS) {
            log_line("Partida #$gameId en espera (" . (TELEGRAM_WAIT_SECONDS - $elapsed) . "s restantes).");
            break;
        }

        $players = $player->getByGame($gameId);
        if (count($players) === 0) {
            $db->prepare("UPDATE telegram_runs SET phase='finished' WHERE game_id=?")->execute([$gameId]);
            $bot->sendMessage("😴 Nadie se unió a tiempo, cancelo la partida.");
            log_line("Partida #$gameId cancelada: sin jugadores.");
            break;
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
        break;

    case 'question':
        $state = $game->getState($gameId);
        if ($state['status'] === 'question' && $state['time_left'] > 0) {
            log_line("Partida #$gameId en pregunta (" . $state['time_left'] . "s restantes).");
            break;
        }

        // El tiempo se acabó: revelar resultados si el propio getState() no lo hizo ya.
        // No se anuncia nada aquí — el resumen completo se manda al terminar la partida.
        if ($state['status'] === 'question') $game->showResults($gameId);

        $db->prepare(
            "UPDATE telegram_runs SET phase='results', phase_changed_at=UTC_TIMESTAMP() WHERE game_id=?"
        )->execute([$gameId]);
        log_line("Partida #$gameId: ronda {$state['current_round']} revelada.");
        break;

    case 'results':
        if ($elapsed < TELEGRAM_REVEAL_SECONDS) {
            log_line("Partida #$gameId mostrando resultados (" . (TELEGRAM_REVEAL_SECONDS - $elapsed) . "s restantes).");
            break;
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
        break;
}

flock($lock, LOCK_UN);
