<?php
/**
 * PlayerController.php — Controlador del jugador
 *
 * Gestiona el join a la partida, el estado en tiempo real del jugador
 * y el envío de respuestas de posición.
 */
require_once __DIR__ . '/../Modelo/Game.php';
require_once __DIR__ . '/../Modelo/Player.php';

class PlayerController {
    private Game   $game;
    private Player $player;

    public function __construct() {
        $this->game   = new Game();
        $this->player = new Player();
    }

    /**
     * Une al jugador a una partida.
     * - PIN individual: valida que esté disponible y no se haya usado.
     * - PIN compartido: valida que la partida exista y acepte jugadores.
     */
    public function joinGame(): array {
        $pin        = trim($_POST['pin']  ?? '');
        $name       = trim($_POST['name'] ?? '');
        $avatar     = trim($_POST['avatar'] ?? '');
        $hair       = trim($_POST['hair'] ?? '');
        $glasses    = trim($_POST['glasses'] ?? '');
        $hat        = trim($_POST['hat'] ?? '');
        $headphones = trim($_POST['headphones'] ?? '');
        $facialHair = trim($_POST['facial_hair'] ?? '');
        $glassesPos    = trim($_POST['glasses_pos'] ?? '');
        $hatPos        = trim($_POST['hat_pos'] ?? '');
        $facialHairPos = trim($_POST['facial_hair_pos'] ?? '');

        if (strlen($pin) !== 4 || !ctype_digit($pin)) return ['error' => 'PIN inválido (4 dígitos)'];
        if ($name === '' || strlen($name) > 30)        return ['error' => 'Nombre inválido (máx 30 caracteres)'];

        // Buscar primero como PIN individual — el email viene guardado en la tabla
        $gameByIndiv = $this->game->getByIndividualPin($pin);
        if ($gameByIndiv) {
            if ($gameByIndiv['status'] !== 'waiting') return ['error' => 'La partida ya ha comenzado'];

            // Crear jugador y marcar el PIN como usado
            $email = $gameByIndiv['player_email'] ?? '';
            $pl    = $this->player->create(
                (int)$gameByIndiv['id'], $name, $email, $avatar, $hair, $glasses, $hat, $headphones, $facialHair,
                $glassesPos, $hatPos, $facialHairPos
            );
            $this->game->claimIndividualPin($pin, $pl['id']);

            return [
                'success'      => true,
                'player_id'    => $pl['id'],
                'game_id'      => (int)$gameByIndiv['id'],
                'player_name'  => $name,
                'player_color' => $pl['color'],
                'player_avatar'=> $pl['avatar'],
            ];
        }

        // Buscar como PIN compartido de sala
        $game = $this->game->getByPin($pin);
        if (!$game) return ['error' => 'PIN no encontrado'];
        if ($game['status'] !== 'waiting') return ['error' => 'La partida ya ha comenzado'];

        // Evitar que alguien entre con el PIN de sala en una partida de PINs individuales
        if (($game['pin_mode'] ?? 'shared') === 'individual') {
            return ['error' => 'Esta partida usa PINs individuales. Usa tu código personal.'];
        }

        $pl = $this->player->create(
            (int)$game['id'], $name, '', $avatar, $hair, $glasses, $hat, $headphones, $facialHair,
            $glassesPos, $hatPos, $facialHairPos
        );
        return [
            'success'      => true,
            'player_id'    => $pl['id'],
            'game_id'      => (int)$game['id'],
            'player_name'  => $name,
            'player_color' => $pl['color'],
            'player_avatar'=> $pl['avatar'],
        ];
    }

    /** Cambia el avatar del jugador — solo permitido mientras la partida está en la sala de espera */
    public function updateAvatar(): array {
        $playerId = (int)($_POST['player_id'] ?? 0);
        $avatar   = trim($_POST['avatar'] ?? '');
        if (!$playerId) return ['error' => 'Falta player_id'];

        $pl = $this->player->getById($playerId);
        if (!$pl) return ['error' => 'Jugador no encontrado'];

        $game = $this->game->getById((int)$pl['game_id']);
        if (!$game || $game['status'] !== 'waiting') {
            return ['error' => 'Solo puedes cambiar de avatar en la sala de espera'];
        }

        if (!$this->player->updateAvatar($playerId, $avatar)) return ['error' => 'Avatar no válido'];
        return ['success' => true, 'avatar' => $avatar];
    }

    /** Vota un género para la partida — solo si la partida tiene la votación activada y sigue en espera */
    public function voteGenre(): array {
        $playerId = (int)($_POST['player_id'] ?? 0);
        $genre    = trim($_POST['genre'] ?? '');
        if (!$playerId) return ['error' => 'Falta player_id'];

        require_once __DIR__ . '/../Modelo/Genres.php';
        if (!in_array($genre, Genres::allWithTodos(), true)) return ['error' => 'Género inválido'];

        $pl = $this->player->getById($playerId);
        if (!$pl) return ['error' => 'Jugador no encontrado'];

        $game = $this->game->getById((int)$pl['game_id']);
        if (!$game || $game['status'] !== 'waiting') {
            return ['error' => 'Solo puedes votar en la sala de espera'];
        }
        if (empty($game['genre_vote_enabled'])) {
            return ['error' => 'Esta partida no usa votación de género'];
        }

        $this->player->setGenreVote($playerId, $genre);
        return ['success' => true, 'genre' => $genre];
    }

    /** Cambia los complementos (pelo, gafas, sombrero, auriculares, vello facial) y sus posiciones — solo en la sala de espera */
    public function updateCustomization(): array {
        $playerId   = (int)($_POST['player_id'] ?? 0);
        $hair       = trim($_POST['hair'] ?? '');
        $glasses    = trim($_POST['glasses'] ?? '');
        $hat        = trim($_POST['hat'] ?? '');
        $headphones = trim($_POST['headphones'] ?? '');
        $facialHair = trim($_POST['facial_hair'] ?? '');
        $glassesPos    = trim($_POST['glasses_pos'] ?? '');
        $hatPos        = trim($_POST['hat_pos'] ?? '');
        $facialHairPos = trim($_POST['facial_hair_pos'] ?? '');
        if (!$playerId) return ['error' => 'Falta player_id'];

        $pl = $this->player->getById($playerId);
        if (!$pl) return ['error' => 'Jugador no encontrado'];

        $game = $this->game->getById((int)$pl['game_id']);
        if (!$game || $game['status'] !== 'waiting') {
            return ['error' => 'Solo puedes personalizar tu avatar en la sala de espera'];
        }

        $this->player->updateCustomization(
            $playerId, $hair, $glasses, $hat, $headphones, $facialHair, $glassesPos, $hatPos, $facialHairPos
        );
        return [
            'success' => true, 'hair' => $hair, 'glasses' => $glasses,
            'hat' => $hat, 'headphones' => $headphones, 'facial_hair' => $facialHair,
            'glasses_pos' => $glassesPos, 'hat_pos' => $hatPos, 'facial_hair_pos' => $facialHairPos,
        ];
    }

    /**
     * Devuelve el estado completo del jugador para su polling.
     * Incluye: estado de la partida, su timeline, si ya respondió,
     * la canción actual (sin año durante la pregunta), resultados de ronda,
     * ranking y leaderboard final.
     */
    public function getPlayerState(): array {
        $playerId = (int)($_GET['player_id'] ?? 0);
        if (!$playerId) return ['error' => 'Falta player_id'];

        $pl = $this->player->getById($playerId);
        if (!$pl) return ['error' => 'Jugador no encontrado'];

        // Actualizar timestamp de actividad
        $this->player->ping($playerId);

        $gameId   = (int)$pl['game_id'];
        $state    = $this->game->getState($gameId);
        $gameType = $state['game_type'] ?? 'song';
        $players  = $this->player->getByGame($gameId);

        // Calcular posición en el ranking contando jugadores con más puntos
        $rank = 1;
        foreach ($players as $p) {
            if ((int)$p['score'] > (int)$pl['score']) $rank++;
        }

        $state['player']        = $pl;
        $state['player_rank']   = $rank;
        $state['total_players'] = count($players);
        $state['has_answered']  = false;
        $state['timeline']      = $this->player->getTimeline($playerId, $gameId, $gameType);

        // Datos específicos del estado de pregunta o resultados
        if (in_array($state['status'], ['question', 'results'], true)) {
            $song = $this->game->getCurrentSong($gameId);
            if ($song) {
                $state['has_answered'] = $this->player->hasAnswered($playerId, $gameId, (int)$song['id'], $gameType);

                if ($state['status'] === 'question') {
                    // Ocultar el año durante la pregunta para que no haga trampa
                    $songForPlayer = $song;
                    unset($songForPlayer['year']);
                    $state['song'] = $songForPlayer;
                } else {
                    // En resultados sí se muestra el año y los resultados de la ronda
                    $state['song']         = $song;
                    $results               = $this->player->getRoundResults($gameId, (int)$song['id'], $gameType);
                    $state['round_results'] = $results;
                    // Buscar el resultado específico de este jugador
                    foreach ($results as $r) {
                        if ($r['name'] === $pl['name']) { $state['my_result'] = $r; break; }
                    }
                }
            }
        }

        // En la pantalla final, enviar el leaderboard completo
        if ($state['status'] === 'finished') {
            $state['leaderboard'] = $players;
        }

        return $state;
    }

    /**
     * Procesa la respuesta de posición del jugador.
     * Valida que la partida esté en fase de pregunta y que no haya respondido ya.
     */
    public function submitAnswer(): array {
        $playerId = (int)($_POST['player_id'] ?? 0);
        $position = (int)($_POST['position']  ?? -1);

        if ($position < 0) return ['error' => 'Posición inválida'];

        $pl = $this->player->getById($playerId);
        if (!$pl) return ['error' => 'Jugador no encontrado'];

        $gameId   = (int)$pl['game_id'];
        $state    = $this->game->getState($gameId);
        $gameType = $state['game_type'] ?? 'song';

        if ($state['status'] !== 'question') return ['error' => 'No hay ronda activa'];

        $song = $this->game->getCurrentSong($gameId);
        if (!$song) return ['error' => 'Sin canción activa'];

        if ($this->player->hasAnswered($playerId, $gameId, (int)$song['id'], $gameType)) {
            return ['error' => 'Ya has respondido esta ronda'];
        }

        return $this->player->submitPositionAnswer(
            $playerId, $gameId, (int)$song['id'],
            $position, (int)$song['year'],
            (int)$state['time_left'], (int)$state['question_time'],
            $gameType
        );
    }
}
