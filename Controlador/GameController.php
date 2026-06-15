<?php
require_once __DIR__ . '/../Modelo/Game.php';
require_once __DIR__ . '/../Modelo/Player.php';

class GameController {
    private Game   $game;
    private Player $player;

    public function __construct() {
        $this->game   = new Game();
        $this->player = new Player();
    }

    public function createGame(): array {
        $rounds       = max(5, min(20, (int)($_POST['total_rounds']   ?? 10)));
        $questionTime = max(20, min(90, (int)($_POST['question_time'] ?? 30)));
        $genre        = in_array($_POST['genre'] ?? '', GENRES, true) ? $_POST['genre'] : 'Todos';
        $showLinks    = ($_POST['show_links']    ?? '0') === '1' ? 1 : 0;
        $embedYoutube = ($_POST['embed_youtube'] ?? '0') === '1' ? 1 : 0;
        $autoplay     = ($_POST['autoplay']      ?? '0') === '1' ? 1 : 0;
        $pinMode      = ($_POST['pin_mode'] ?? 'shared') === 'individual' ? 'individual' : 'shared';
        $email        = filter_var(trim($_POST['organizer_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
        $indivCount   = max(2, min(30, (int)($_POST['individual_count'] ?? 2)));

        $prize1 = substr(trim($_POST['prize_1'] ?? ''), 0, 200);
        $prize2 = substr(trim($_POST['prize_2'] ?? ''), 0, 200);
        $prize3 = substr(trim($_POST['prize_3'] ?? ''), 0, 200);

        $result = $this->game->create(
            $rounds, $questionTime, $genre, $showLinks, $embedYoutube, $autoplay,
            $pinMode, $email, $pinMode === 'individual' ? $indivCount : 0,
            $prize1, $prize2, $prize3
        );

        if (empty($result['error'])) {
            require_once __DIR__ . '/../Modelo/EmailService.php';
            if ($pinMode === 'shared' && $email) {
                EmailService::sendGameCreated($email, $result['pin'], BASE_URL, 'shared', []);
            } elseif ($pinMode === 'individual' && !empty($result['individual_pins'])) {
                $playerEmails = $_POST['player_emails'] ?? [];
                foreach ($result['individual_pins'] as $idx => $pin) {
                    $pEmail = filter_var(trim($playerEmails[$idx] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
                    if ($pEmail) EmailService::sendPlayerPin($pEmail, $pin, BASE_URL, $idx + 1);
                }
            }
        }

        return $result;
    }

    public function getGameState(): array {
        $gameId = (int)($_GET['game_id'] ?? 0);
        if (!$gameId) return ['error' => 'Falta game_id'];

        $state   = $this->game->getState($gameId);
        $players = $this->player->getByGame($gameId);

        $state['players']      = $players;
        $state['player_count'] = count($players);

        if (in_array($state['status'], ['question','results'], true)) {
            $song = $this->game->getCurrentSong($gameId);
            if ($song) {
                $state['song']         = $song; // Admin ve el año siempre
                $state['answer_count'] = $this->player->getAnswerCount($gameId, (int)$song['id']);
                if ($state['status'] === 'results') {
                    $state['round_results'] = $this->player->getRoundResults($gameId, (int)$song['id']);
                }
            }
        }

        return $state;
    }

    public function getGameStateByPin(): array {
        $pin = trim($_GET['pin'] ?? '');
        if (strlen($pin) !== 4 || !ctype_digit($pin)) return ['error' => 'PIN inválido'];

        $game = $this->game->getByPin($pin);
        if (!$game) {
            // Intentar por PIN individual
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

    public function startGame(): array {
        $gameId = (int)($_POST['game_id']     ?? 0);
        $token  = $_POST['admin_token'] ?? '';
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];
        $game = $this->game->getById($gameId);
        if (!$game)                          return ['error' => 'Partida no encontrada'];
        if ($game['status'] !== 'waiting')   return ['error' => 'La partida ya ha comenzado'];

        $this->game->start($gameId);
        return ['success' => true];
    }

    public function showResults(): array {
        $gameId = (int)($_POST['game_id']     ?? 0);
        $token  = $_POST['admin_token'] ?? '';
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];
        $this->game->showResults($gameId);
        return ['success' => true];
    }

    public function nextRound(): array {
        $gameId = (int)($_POST['game_id']     ?? 0);
        $token  = $_POST['admin_token'] ?? '';
        if (!$this->game->verifyAdmin($gameId, $token)) return ['error' => 'No autorizado'];
        $newStatus = $this->game->nextRound($gameId);
        return ['success' => true, 'new_status' => $newStatus];
    }
}
