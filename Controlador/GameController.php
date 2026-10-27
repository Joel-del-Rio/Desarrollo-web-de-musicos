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
require_once __DIR__ . '/../Modelo/Planner.php';
require_once __DIR__ . '/../Modelo/LiveGame.php';

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
        $rounds       = max(5,  min(50, (int)($_POST['total_rounds']   ?? 10)));
        $genre        = in_array($_POST['genre'] ?? '', Genres::allWithTodos(), true) ? $_POST['genre'] : 'Todos';
        $gameType     = ($_POST['game_type'] ?? 'song') === 'meme' ? 'meme' : 'song';
        // El modo memes admite rondas más largas (hasta 120s) que el de canciones (hasta 60s)
        $maxTime      = $gameType === 'meme' ? 120 : 60;
        $questionTime = max(20, min($maxTime, (int)($_POST['question_time'] ?? 30)));
        // El modo memes no tiene audio ni links de streaming — se fuerzan a 0 al margen de lo enviado
        $showLinks    = $gameType === 'meme' ? 0 : (($_POST['show_links']    ?? '0') === '1' ? 1 : 0);
        $embedYoutube = $gameType === 'meme' ? 0 : (($_POST['embed_youtube'] ?? '0') === '1' ? 1 : 0);
        $autoplay     = $gameType === 'meme' ? 0 : (($_POST['autoplay']      ?? '0') === '1' ? 1 : 0);
        $hardMode     = ($_POST['hard_mode']     ?? '0') === '1' ? 1 : 0;
        $pinMode      = ($_POST['pin_mode'] ?? 'shared') === 'individual' ? 'individual' : 'shared';
        $email        = filter_var(trim($_POST['organizer_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
        $indivCount = filter_var($_POST['individual_count'] ?? 0, FILTER_VALIDATE_INT);
        if ($indivCount === false || $indivCount < 0 || $indivCount > 500) return ['error'=>'Genera de 0 a 500 códigos inicialmente; después puedes añadir más'];
        $hostHash = Planner::hostHash((string)($_POST['host_key'] ?? bin2hex(random_bytes(32))));
        [$title,$plannedAt] = Planner::details($_POST);
        $rawOrganizer = trim((string)($_POST['organizer_email'] ?? ''));
        if ($rawOrganizer !== '' && !$email) return ['error'=>'Correo del organizador no válido'];
        $isPublic     = ($_POST['is_public'] ?? '0') === '1';

        // Emails de los jugadores (modo individual) — se validan uno a uno
        if (!is_array($_POST['player_emails'] ?? [])) return ['error'=>'Lista de correos no válida'];
        foreach (($_POST['player_emails'] ?? []) as $value) {
            if (!is_string($value) || (trim($value)!=='' && !filter_var(trim($value),FILTER_VALIDATE_EMAIL))) return ['error'=>'Revisa los correos de los jugadores'];
        }
        $playerEmails = array_values(array_map(
            fn($e) => filter_var(trim($e), FILTER_VALIDATE_EMAIL) ?: '',
            $_POST['player_emails'] ?? []
        ));

        $db = Database::getInstance()->pdo();
        if (!(int)$db->query("SELECT GET_LOCK('hitstoric_pin_allocator',10)")->fetchColumn()) return ['error'=>'Servidor ocupado. Vuelve a intentarlo'];
        $db->beginTransaction();
        try {
        $result = $this->game->create(
            $rounds, $questionTime, $genre, $showLinks, $embedYoutube, $autoplay,
            $pinMode, $email, $pinMode === 'individual' ? $indivCount : 0,
            '', '', '',
            $playerEmails,
            $hardMode,
            $gameType,
            $isPublic
        );

        $db->prepare('UPDATE games SET host_key_hash=?,title=?,planned_at=? WHERE id=?')->execute([$hostHash,$title,$plannedAt,$result['id']]);
        $db->prepare('UPDATE games SET live_mode=? WHERE id=?')->execute([$gameType==='song' && ($_POST['live_mode']??'0')==='1'?1:0,$result['id']]);
        require_once __DIR__.'/../Modelo/GamePrizes.php';
        GamePrizes::defaults($db,$this->game->getById($result['id']));
        if (($_POST['send_emails'] ?? '0') === '1') {
            if ($pinMode==='shared' && $email) MailQueue::enqueue($db,$result['id'],null,$email,$result['pin'],'organizer');
            if ($pinMode==='individual') {
                foreach ((new Planner())->invitations($result['id']) as $inv) {
                    if ($inv['email']) MailQueue::enqueue($db,$result['id'],(int)$inv['id'],$inv['email'],$inv['pin']);
                }
            }
        }
        $db->commit();
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
        finally { $db->query("SELECT RELEASE_LOCK('hitstoric_pin_allocator')"); }
        if (empty($result['error'])) {
            // Partida pública: anunciar el PIN, el enlace y los detalles de la partida en Telegram/Discord
            if ($isPublic) {
                $joinUrl = BASE_URL . '/player?pin=' . $result['pin'];
                $kind    = $gameType === 'meme' ? '😂 Memes' : '🎵 Canciones';
                $details = "{$kind} · {$genre}\n" .
                           "🔢 {$rounds} rondas · ⏱️ {$questionTime}s por ronda" .
                           ($hardMode ? "\n🔥 Modo difícil" : '');

                if (defined('TELEGRAM_ENABLED') && TELEGRAM_ENABLED) {
                    require_once __DIR__ . '/../Modelo/TelegramBot.php';
                    $bot = new TelegramBot(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID);
                    $bot->sendMessage(
                        "🎮 *¡Nueva partida pública de Hitstoric!*\n" .
                        "PIN: `{$result['pin']}`\n" .
                        "Únete aquí: {$joinUrl}\n\n" .
                        $details
                    );
                }

                if (defined('DISCORD_ENABLED') && DISCORD_ENABLED) {
                    require_once __DIR__ . '/../Modelo/DiscordWebhook.php';
                    $webhook = new DiscordWebhook(DISCORD_WEBHOOK_URL);
                    $webhook->sendMessage(
                        "🎮 **¡Nueva partida pública de Hitstoric!**\n" .
                        "PIN: `{$result['pin']}`\n" .
                        "Únete aquí: {$joinUrl}\n\n" .
                        $details
                    );
                }
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
     *
     * Requiere el admin_token: la respuesta incluye el año de la canción en curso,
     * que en player_state se oculta durante la pregunta. Sin esta comprobación
     * cualquier jugador podría leer aquí la solución antes de responder.
     */
    public function getGameState(): array {
        $gameId = (int)($_GET['game_id'] ?? 0);
        $token  = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['admin_token'] ?? $_POST['admin_token'] ?? '';
        if (!$gameId) return ['error' => 'Falta game_id'];
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];

        $state    = $this->game->getState($gameId);
        $gameType = $state['game_type'] ?? 'song';
        $players  = $this->player->getByGame($gameId);

        $record = $this->game->getById($gameId);
        $pins=Database::getInstance()->pdo()->prepare('SELECT player_id,pin FROM individual_pins WHERE game_id=? AND player_id IS NOT NULL AND revoked=0');$pins->execute([$gameId]);$pinMap=$pins->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach($state['event_prizes']['awards'] as &$award)$award['participation_pin']=$pinMap[$award['player_id']]??null;
        unset($award);
        $state['live_output'] = LiveGame::info($record);
        $state['pin'] = $record['pin'];
        $state['title'] = $record['title'];
        $state['planned_at'] = $record['planned_at'];
        if ($state['status']==='waiting' && ($_GET['roster']??'0')==='1') $state['invitations']=(new Planner())->invitations($gameId);
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
        $pin = strtoupper(trim($_GET['pin'] ?? ''));
        if (!Game::validCode($pin)) return ['error' => 'PIN inválido'];

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

        return (new Planner())->waiting($gameId,$token,function($record) use ($gameId) {
            PracticeSession::checkSession($record,$_POST);
            if (!$this->player->getByGame($gameId)) throw new InvalidArgumentException('Debe haber al menos un jugador');
            LiveGame::requireSound($this->game->getById($gameId));
            $this->game->prepareSession($gameId,(string)($_POST['session_kind']??'main'));
            $this->game->start($gameId); return ['success'=>true];
        });
    }

    /** Pasa a modo resultados y muestra el año de la canción */
    public function showResults(): array {
        $gameId = (int)($_POST['game_id'] ?? 0);
        $token  = $_POST['admin_token'] ?? '';
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];
        $db=Database::getInstance()->pdo();$db->beginTransaction();
        try {
            $st=$db->prepare('SELECT * FROM games WHERE id=? FOR UPDATE');$st->execute([$gameId]);$record=$st->fetch();
            PracticeSession::checkSession($record,$_POST);
            if (isset($_POST['expected_round']) && (int)$_POST['expected_round']!==(int)$record['current_round']) throw new InvalidArgumentException('La ronda ha cambiado');
            $this->game->showResults($gameId);$db->commit();return ['success'=>true];
        } finally {if($db->inTransaction())$db->rollBack();}
    }

    /** Avanza a la siguiente ronda o termina la partida */
    public function nextRound(): array {
        $gameId = (int)($_POST['game_id'] ?? 0);
        $token  = $_POST['admin_token'] ?? '';
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];
        $db=Database::getInstance()->pdo();$db->beginTransaction();
        try {
            $st=$db->prepare('SELECT * FROM games WHERE id=? FOR UPDATE');$st->execute([$gameId]);$record=$st->fetch();
            PracticeSession::checkSession($record,$_POST);
            if (!$record || $record['status']!=='results') return ['error'=>'La ronda todavía no ha terminado'];
            if (isset($_POST['expected_round']) && (int)$_POST['expected_round']!==(int)$record['current_round']) return ['error'=>'La ronda ya ha cambiado. Actualiza la pantalla'];
            $currentGame=$this->game->getById($gameId);
            if((int)$currentGame['current_round']<(int)$currentGame['total_rounds'])LiveGame::requireSound($currentGame);
            $newStatus = $this->game->nextRound($gameId);
            $db->commit();return ['success'=>true,'new_status'=>$newStatus];
        } finally { if($db->inTransaction())$db->rollBack(); }
    }

    /** Expulsa a un jugador de la sala de espera (solo el admin autorizado puede hacerlo) */
    public function kickPlayer(): array {
        $gameId   = (int)($_POST['game_id'] ?? 0);
        $token    = $_POST['admin_token'] ?? '';
        $playerId = (int)($_POST['player_id'] ?? 0);
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];
        if (!$playerId) return ['error' => 'Falta player_id'];

        return (new Planner())->waiting($gameId,$token,function() use ($playerId,$gameId) {
            $db=Database::getInstance()->pdo();
            $db->prepare('UPDATE individual_pins SET used=0,player_id=NULL WHERE player_id=? AND game_id=?')->execute([$playerId,$gameId]);
            return $this->player->remove($playerId,$gameId) ? ['success'=>true] : ['error'=>'Jugador no encontrado'];
        });
    }

    /**
     * Añade un jugador bot a la sala de espera, con una edad orientativa que
     * determina qué tan bien "conoce" cada época musical (ver BotBrain).
     * Sirve para probar la jugabilidad de una partida sin gente real.
     */
    public function addBot(): array {
        require_once __DIR__ . '/../Modelo/BotBrain.php';

        $gameId = (int)($_POST['game_id'] ?? 0);
        $token  = $_POST['admin_token'] ?? '';
        $age    = (int)($_POST['age'] ?? 0);
        $name   = trim($_POST['name'] ?? '');

        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];

        $game = $this->game->getById($gameId);
        if (!$game || $game['status'] !== 'waiting') {
            return ['error' => 'Solo puedes añadir bots en la sala de espera'];
        }
        if ($age < BotBrain::MIN_AGE || $age > BotBrain::MAX_AGE) return ['error' => 'Edad inválida'];

        if ($name === '') $name = "Bot {$age} años";
        $name = mb_substr($name, 0, 30);

        $bot = $this->player->createBot($gameId, $name, $age);
        return ['success' => true, 'player_id' => $bot['id'], 'name' => $bot['name']];
    }
}
