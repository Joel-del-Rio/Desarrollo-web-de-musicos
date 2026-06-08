<?php
require_once __DIR__ . '/../Modelo/Game.php';
require_once __DIR__ . '/../Modelo/Player.php';

class PlayerController {
    private Game   $game;
    private Player $player;

    public function __construct() {
        $this->game   = new Game();
        $this->player = new Player();
    }

    public function joinGame(): array {
        $pin  = trim($_POST['pin']  ?? '');
        $name = trim($_POST['name'] ?? '');

        if (strlen($pin) !== 4 || !ctype_digit($pin)) return ['error' => 'PIN inválido (4 dígitos)'];
        if ($name === '' || strlen($name) > 30)         return ['error' => 'Nombre inválido (máx 30 caracteres)'];

        $game = $this->game->getByPin($pin);
        if (!$game)                         return ['error' => 'Partida no encontrada'];
        if ($game['status'] !== 'waiting')  return ['error' => 'La partida ya ha comenzado'];

        $pl = $this->player->create((int)$game['id'], $name);
        return [
            'success'      => true,
            'player_id'    => $pl['id'],
            'game_id'      => (int)$game['id'],
            'player_name'  => $name,
            'player_color' => $pl['color'],
        ];
    }

    public function getPlayerState(): array {
        $playerId = (int)($_GET['player_id'] ?? 0);
        if (!$playerId) return ['error' => 'Falta player_id'];

        $pl = $this->player->getById($playerId);
        if (!$pl) return ['error' => 'Jugador no encontrado'];

        $this->player->ping($playerId);

        $gameId  = (int)$pl['game_id'];
        $state   = $this->game->getState($gameId);
        $players = $this->player->getByGame($gameId);

        // Calcular ranking del jugador
        $rank = 1;
        foreach ($players as $p) {
            if ((int)$p['score'] > (int)$pl['score']) $rank++;
        }

        $state['player']        = $pl;
        $state['player_rank']   = $rank;
        $state['total_players'] = count($players);
        $state['has_answered']  = false;

        if (in_array($state['status'], ['question', 'results', 'answered'], true)) {
            $song = $this->game->getCurrentSong($gameId);
            if ($song) {
                $state['has_answered'] = $this->player->hasAnswered($playerId, $gameId, (int)$song['id']);

                if ($state['status'] === 'question') {
                    // No revelar el año correcto al jugador
                    $songForPlayer = $song;
                    unset($songForPlayer['year']);
                    $state['song'] = $songForPlayer;
                } else {
                    $state['song']          = $song;
                    $state['round_results'] = $this->player->getRoundResults($gameId, (int)$song['id']);
                    // Resultado personal de esta ronda
                    foreach ($state['round_results'] as $r) {
                        if ($r['name'] === $pl['name']) {
                            $state['my_result'] = $r;
                            break;
                        }
                    }
                }
            }
        }

        if ($state['status'] === 'finished') {
            $state['leaderboard'] = $players;
        }

        return $state;
    }

    public function submitAnswer(): array {
        $playerId  = (int)($_POST['player_id']  ?? 0);
        $yearGuess = (int)($_POST['year_guess'] ?? 0);

        $pl = $this->player->getById($playerId);
        if (!$pl) return ['error' => 'Jugador no encontrado'];

        $gameId = (int)$pl['game_id'];
        $state  = $this->game->getState($gameId);

        if ($state['status'] !== 'question') return ['error' => 'No hay pregunta activa'];

        $song = $this->game->getCurrentSong($gameId);
        if (!$song) return ['error' => 'Sin canción activa'];

        if ($this->player->hasAnswered($playerId, $gameId, (int)$song['id'])) {
            return ['error' => 'Ya has respondido esta ronda'];
        }

        return $this->player->submitAnswer(
            $playerId, $gameId, (int)$song['id'],
            $yearGuess, (int)$song['year'], (int)$state['time_left']
        );
    }
}
