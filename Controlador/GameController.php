<?php
/**
 * GameController.php — Controlador de partida
 *
 * Recibe las peticiones del panel de admin, valida los datos de entrada
 * y delega la lógica al modelo Game. También envía el email de confirmación
 * al organizador al crear la partida.
 */
require_once __DIR__ . '/../Modelo/Game.php';
require_once __DIR__ . '/../Modelo/Player.php';
require_once __DIR__ . '/../Modelo/Genres.php';

class GameController {
    private Game   $game;
    private Player $player;

    public function __construct() {
        $this->game   = new Game();
        $this->player = new Player();
    }

    /**
     * Crea una nueva partida con los parámetros del formulario de configuración.
     * Si se proporcionaron emails, envía los PINs por correo tras crear la partida.
     */
    public function createGame(): array {
        // Validar y sanitizar parámetros (con rangos mínimos/máximos)
        $rounds       = max(5,  min(20, (int)($_POST['total_rounds']   ?? 10)));
        $questionTime = max(20, min(60, (int)($_POST['question_time'] ?? 30)));
        $genre        = in_array($_POST['genre'] ?? '', Genres::allWithTodos(), true) ? $_POST['genre'] : 'Todos';
        $gameType     = ($_POST['game_type'] ?? 'song') === 'meme' ? 'meme' : 'song';
        // El modo memes no tiene audio ni links de streaming — se fuerzan a 0 al margen de lo enviado
        $showLinks    = $gameType === 'meme' ? 0 : (($_POST['show_links']    ?? '0') === '1' ? 1 : 0);
        $embedYoutube = $gameType === 'meme' ? 0 : (($_POST['embed_youtube'] ?? '0') === '1' ? 1 : 0);
        $autoplay     = $gameType === 'meme' ? 0 : (($_POST['autoplay']      ?? '0') === '1' ? 1 : 0);
        $hardMode     = ($_POST['hard_mode']     ?? '0') === '1' ? 1 : 0;
        $pinMode      = ($_POST['pin_mode'] ?? 'shared') === 'individual' ? 'individual' : 'shared';
        $email        = filter_var(trim($_POST['organizer_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
        $indivCount   = max(2, min(30, (int)($_POST['individual_count'] ?? 2)));
        $isPublic     = ($_POST['is_public'] ?? '0') === '1';

        // Emails de los jugadores (modo individual) — se validan uno a uno
        $playerEmails = array_values(array_map(
            fn($e) => filter_var(trim($e), FILTER_VALIDATE_EMAIL) ?: '',
            $_POST['player_emails'] ?? []
        ));

        $result = $this->game->create(
            $rounds, $questionTime, $genre, $showLinks, $embedYoutube, $autoplay,
            $pinMode, $email, $pinMode === 'individual' ? $indivCount : 0,
            '', '', '',
            $playerEmails,
            $hardMode,
            $gameType,
            $isPublic
        );

        // Enviar emails de confirmación si la partida se creó correctamente
        if (empty($result['error'])) {
            require_once __DIR__ . '/../Modelo/EmailService.php';

            if ($pinMode === 'shared' && $email) {
                // Modo compartido: un solo email al organizador con el PIN de sala
                EmailService::sendGameCreated($email, $result['pin'], BASE_URL, 'shared', []);

            } elseif ($pinMode === 'individual' && !empty($result['individual_pins'])) {
                // Modo individual: un email a cada jugador con su PIN personal
                foreach ($result['individual_pins'] as $idx => $pin) {
                    $pEmail = $playerEmails[$idx] ?? '';
                    if ($pEmail) EmailService::sendPlayerPin($pEmail, $pin, BASE_URL, $idx + 1);
                }
            }

            // Partida pública: anunciar el PIN, el enlace y los detalles de la partida en Telegram
            if ($isPublic && defined('TELEGRAM_ENABLED') && TELEGRAM_ENABLED) {
                require_once __DIR__ . '/../Modelo/TelegramBot.php';
                $bot     = new TelegramBot(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID);
                $joinUrl = BASE_URL . '/player?pin=' . $result['pin'];
                $kind    = $gameType === 'meme' ? '😂 Memes' : '🎵 Canciones';
                $bot->sendMessage(
                    "🎮 *¡Nueva partida pública de Hitstoric!*\n" .
                    "PIN: `{$result['pin']}`\n" .
                    "Únete aquí: {$joinUrl}\n\n" .
                    "{$kind} · {$genre}\n" .
                    "🔢 {$rounds} rondas · ⏱️ {$questionTime}s por ronda" .
                    ($hardMode ? "\n🔥 Modo difícil" : '')
                );
            }
        }

        return $result;
    }

    /** Lista las partidas públicas abiertas para el navegador de servidores del jugador */
    public function listPublicGames(): array {
        return ['success' => true, 'games' => $this->game->listPublic()];
    }

    /**
     * Devuelve el estado completo de la partida para el polling del admin.
     * Incluye jugadores, conteo de respuestas y resultados de ronda si aplica.
     */
    public function getGameState(): array {
        $gameId = (int)($_GET['game_id'] ?? 0);
        if (!$gameId) return ['error' => 'Falta game_id'];

        $state    = $this->game->getState($gameId);
        $gameType = $state['game_type'] ?? 'song';
        $players  = $this->player->getByGame($gameId);

        $state['players']      = $players;
        $state['player_count'] = count($players);

        // Durante la pregunta o resultados, añadir datos de la canción actual
        if (in_array($state['status'], ['question', 'results'], true)) {
            $song = $this->game->getCurrentSong($gameId);
            if ($song) {
                $state['song']         = $song; // Admin ve el año siempre
                $state['answer_count'] = $this->player->getAnswerCount($gameId, (int)$song['id'], $gameType);
                if ($state['status'] === 'results') {
                    $state['round_results'] = $this->player->getRoundResults($gameId, (int)$song['id'], $gameType);
                }
            }
        }

        return $state;
    }

    /**
     * Devuelve el estado de la partida usando el PIN (para el join del jugador).
     * Prueba primero como PIN individual y luego como PIN de sala.
     */
    public function getGameStateByPin(): array {
        $pin = trim($_GET['pin'] ?? '');
        if (strlen($pin) !== 4 || !ctype_digit($pin)) return ['error' => 'PIN inválido'];

        $game = $this->game->getByPin($pin);
        if (!$game) {
            $game = $this->game->getByIndividualPin($pin);
            if (!$game) return ['error' => 'PIN no encontrado o partida finalizada'];
        }

        $gameId  = (int)$game['id'];
        $state   = $this->game->getState($gameId);
        $players = $this->player->getByGame($gameId);

        $state['id']           = $gameId;
        $state['players']      = $players;
        $state['player_count'] = count($players);
        return $state;
    }

    /** Inicia la partida (solo el admin autorizado puede hacerlo) */
    public function startGame(): array {
        $gameId = (int)($_POST['game_id'] ?? 0);
        $token  = $_POST['admin_token'] ?? '';
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];

        $game = $this->game->getById($gameId);
        if (!$game)                        return ['error' => 'Partida no encontrada'];
        if ($game['status'] !== 'waiting') return ['error' => 'La partida ya ha comenzado'];

        $this->game->start($gameId);
        return ['success' => true];
    }

    /** Pasa a modo resultados y muestra el año de la canción */
    public function showResults(): array {
        $gameId = (int)($_POST['game_id'] ?? 0);
        $token  = $_POST['admin_token'] ?? '';
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];
        $this->game->showResults($gameId);
        return ['success' => true];
    }

    /** Avanza a la siguiente ronda o termina la partida */
    public function nextRound(): array {
        $gameId = (int)($_POST['game_id'] ?? 0);
        $token  = $_POST['admin_token'] ?? '';
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];
        $newStatus = $this->game->nextRound($gameId);
        return ['success' => true, 'new_status' => $newStatus];
    }

    /** Expulsa a un jugador de la sala de espera (solo el admin autorizado puede hacerlo) */
    public function kickPlayer(): array {
        $gameId   = (int)($_POST['game_id'] ?? 0);
        $token    = $_POST['admin_token'] ?? '';
        $playerId = (int)($_POST['player_id'] ?? 0);
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];
        if (!$playerId) return ['error' => 'Falta player_id'];

        $ok = $this->player->remove($playerId, $gameId);
        return $ok ? ['success' => true] : ['error' => 'No se pudo eliminar al jugador'];
    }
}
