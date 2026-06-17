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
        if ($name === '' || strlen($name) > 30)        return ['error' => 'Nombre inválido (máx 30 caracteres)'];

        // Primero buscar como PIN individual
        $gameByIndiv = $this->game->getByIndividualPin($pin);
        if ($gameByIndiv) {
            if ($gameByIndiv['status'] !== 'waiting') return ['error' => 'La partida ya ha comenzado'];

            // PIN individual = de pago → redirigir a Stripe (salvo que esté desactivado)
            if (STRIPE_ENABLED) {
                require_once __DIR__ . '/../Modelo/StripeHelper.php';
                $session = StripeHelper::createCheckoutSession($pin, $name);
                if (empty($session['url'])) {
                    $msg = $session['error']['message'] ?? 'Error al crear sesión de pago';
                    return ['error' => $msg];
                }
                return ['checkout_url' => $session['url']];
            }

            // Local sin Stripe: unirse directamente
            $email = $gameByIndiv['player_email'] ?? '';
            $pl    = $this->player->create((int)$gameByIndiv['id'], $name, $email);
            $this->game->claimIndividualPin($pin, $pl['id']);
            return [
                'success'      => true,
                'player_id'    => $pl['id'],
                'game_id'      => (int)$gameByIndiv['id'],
                'player_name'  => $name,
                'player_color' => $pl['color'],
            ];
        }

        // Buscar como PIN compartido — sin email
        $game = $this->game->getByPin($pin);
        if (!$game) return ['error' => 'PIN no encontrado'];
        if ($game['status'] !== 'waiting') return ['error' => 'La partida ya ha comenzado'];
        if (($game['pin_mode'] ?? 'shared') === 'individual') {
            return ['error' => 'Esta partida usa PINs individuales. Usa tu código personal.'];
        }

        $pl = $this->player->create((int)$game['id'], $name);
        return [
            'success'      => true,
            'player_id'    => $pl['id'],
            'game_id'      => (int)$game['id'],
            'player_name'  => $name,
            'player_color' => $pl['color'],
        ];
    }

    public function completeJoin(): array {
        $sessionId = trim($_POST['stripe_session'] ?? '');
        if (!$sessionId) return ['error' => 'Sesión de pago inválida'];

        require_once __DIR__ . '/../Modelo/StripeHelper.php';
        $session = StripeHelper::getSession($sessionId);

        if (($session['payment_status'] ?? '') !== 'paid') {
            return ['error' => 'El pago no se ha completado'];
        }

        $pin  = $session['metadata']['pin']         ?? '';
        $name = $session['metadata']['player_name'] ?? '';
        if (!$pin || !$name) return ['error' => 'Datos de sesión incompletos'];

        $gameByIndiv = $this->game->getByIndividualPin($pin);
        if (!$gameByIndiv) return ['error' => 'PIN ya usado o no encontrado'];
        if ($gameByIndiv['status'] !== 'waiting') return ['error' => 'La partida ya ha comenzado'];

        $email = $gameByIndiv['player_email'] ?? '';
        $pl    = $this->player->create((int)$gameByIndiv['id'], $name, $email);
        $this->game->claimIndividualPin($pin, $pl['id']);
        $this->player->ping($pl['id']);

        return [
            'success'   => true,
            'player_id' => $pl['id'],
            'game_id'   => (int)$gameByIndiv['id'],
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

        // Ranking del jugador
        $rank = 1;
        foreach ($players as $p) { if ((int)$p['score'] > (int)$pl['score']) $rank++; }

        $state['player']        = $pl;
        $state['player_rank']   = $rank;
        $state['total_players'] = count($players);
        $state['has_answered']  = false;
        $state['timeline']      = $this->player->getTimeline($playerId, $gameId);

        if (in_array($state['status'], ['question','results'], true)) {
            $song = $this->game->getCurrentSong($gameId);
            if ($song) {
                $state['has_answered'] = $this->player->hasAnswered($playerId, $gameId, (int)$song['id']);

                if ($state['status'] === 'question') {
                    // Ocultar año al jugador durante la pregunta
                    $songForPlayer = $song;
                    unset($songForPlayer['year']);
                    $state['song'] = $songForPlayer;
                } else {
                    $state['song']         = $song; // Año visible en resultados
                    $results               = $this->player->getRoundResults($gameId, (int)$song['id']);
                    $state['round_results'] = $results;
                    foreach ($results as $r) {
                        if ($r['name'] === $pl['name']) { $state['my_result'] = $r; break; }
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
        $playerId = (int)($_POST['player_id'] ?? 0);
        $position = (int)($_POST['position']  ?? -1);

        if ($position < 0) return ['error' => 'Posición inválida'];

        $pl = $this->player->getById($playerId);
        if (!$pl) return ['error' => 'Jugador no encontrado'];

        $gameId = (int)$pl['game_id'];
        $state  = $this->game->getState($gameId);

        if ($state['status'] !== 'question') return ['error' => 'No hay ronda activa'];

        $song = $this->game->getCurrentSong($gameId);
        if (!$song) return ['error' => 'Sin canción activa'];

        if ($this->player->hasAnswered($playerId, $gameId, (int)$song['id'])) {
            return ['error' => 'Ya has respondido esta ronda'];
        }

        return $this->player->submitPositionAnswer(
            $playerId, $gameId, (int)$song['id'],
            $position, (int)$song['year'],
            (int)$state['time_left'], (int)$state['question_time']
        );
    }
}
