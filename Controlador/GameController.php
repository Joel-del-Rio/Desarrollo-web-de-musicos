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
        $questionTime = max(10, min(60, (int)($_POST['question_time'] ?? 30)));
        return $this->game->create($rounds, $questionTime);
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
