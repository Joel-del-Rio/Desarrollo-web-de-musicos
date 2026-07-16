<?php
/**
 * Game.php — Modelo de partida
 *
 * Gestiona todo el ciclo de vida de una partida: creación, inicio,
 * avance de rondas, estado actual y consultas de canciones.
 */
require_once __DIR__ . '/Database.php';

class Game {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    // ── Creación ──────────────────────────────────────

    /**
     * Crea una nueva partida con sus canciones y, si es modo individual,
     * genera los PINs personales de cada jugador.
     *
     * @return array  Datos de la partida: id, pin, admin_token, pin_mode,
     *                y opcionalmente individual_pins[]
     */
    public function create(
        int $totalRounds, int $questionTime, string $genre = 'Todos',
        int $showLinks = 0, int $embedYoutube = 0, int $autoplay = 0,
        string $pinMode = 'shared', string $organizerEmail = '',
        int $individualCount = 0,
        string $prize1 = '', string $prize2 = '', string $prize3 = '',
        array $playerEmails = [],
        int $hardMode = 0,
        string $gameType = 'song',
        bool $isPublic = false
    ): array {
        // Generar PIN único de 4 dígitos (no repetir PINs de partidas activas)
        do {
            $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $st  = $this->db->prepare("SELECT id FROM games WHERE pin=? AND status!='finished'");
            $st->execute([$pin]);
        } while ($st->fetch());

        // Token secreto del dinamizador para autenticar acciones de admin
        $token = bin2hex(random_bytes(32));

        $this->db->prepare(
            "INSERT INTO games
             (pin, admin_token, total_rounds, question_time, selected_genre,
              show_links, embed_youtube, autoplay, pin_mode, organizer_email,
              prize_1, prize_2, prize_3, hard_mode, game_type, is_public)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $pin, $token, $totalRounds, $questionTime, $genre,
            $showLinks, $embedYoutube, $autoplay, $pinMode,
            $organizerEmail ?: null, $prize1 ?: null, $prize2 ?: null, $prize3 ?: null,
            $hardMode, $gameType, $isPublic ? 1 : 0,
        ]);
        $gameId = (int)$this->db->lastInsertId();

        $this->assignRoundContent($gameId, $genre, $gameType, $totalRounds);

        $result = ['id' => $gameId, 'pin' => $pin, 'admin_token' => $token, 'pin_mode' => $pinMode];

        // En modo individual, generar un PIN único por jugador
        if ($pinMode === 'individual' && $individualCount > 0) {
            $result['individual_pins'] = $this->generateIndividualPins($gameId, $individualCount, $playerEmails);
        }

        return $result;
    }

    /**
     * Selecciona contenido aleatorio para las rondas (filtrado por género si aplica)
     * y lo asigna a game_songs/game_memes.
     */
    private function assignRoundContent(int $gameId, string $genre, string $gameType, int $totalRounds): void {
        $contentTable = $gameType === 'meme' ? 'memes' : 'songs';
        $gameTable    = $gameType === 'meme' ? 'game_memes' : 'game_songs';
        $itemCol      = $gameType === 'meme' ? 'meme_id' : 'song_id';

        if ($genre === 'Todos') {
            $st = $this->db->prepare("SELECT id FROM {$contentTable} ORDER BY RAND() LIMIT ?");
            $st->execute([$totalRounds]);
        } else {
            $st = $this->db->prepare("SELECT id FROM {$contentTable} WHERE genre=? ORDER BY RAND() LIMIT ?");
            $st->execute([$genre, $totalRounds]);
        }
        $items = $st->fetchAll(PDO::FETCH_COLUMN);

        $ins = $this->db->prepare(
            "INSERT INTO {$gameTable} (game_id, {$itemCol}, round_number) VALUES (?,?,?)"
        );
        foreach ($items as $i => $itemId) {
            $ins->execute([$gameId, $itemId, $i + 1]);
        }
    }

    /**
     * Genera PINs individuales únicos para cada jugador de la partida.
     * Evita colisiones con PINs de partidas activas mediante reintentos.
     */
    private function generateIndividualPins(int $gameId, int $count, array $emails = []): array {
        $pins      = [];
        $ins       = $this->db->prepare("INSERT INTO individual_pins (game_id, pin, email) VALUES (?,?,?)");
        $generated = 0;
        $attempts  = 0;

        while ($generated < $count && $attempts < $count * 20) {
            $attempts++;
            $candidate = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            // Comprobar que no coincida con el PIN general de una partida activa
            $st = $this->db->prepare("SELECT 1 FROM games WHERE pin=? AND status!='finished'");
            $st->execute([$candidate]);
            if ($st->fetch()) continue;

            // Comprobar que no coincida con otro PIN individual activo
            $st = $this->db->prepare(
                "SELECT 1 FROM individual_pins ip
                 JOIN games g ON ip.game_id=g.id
                 WHERE ip.pin=? AND g.status!='finished'"
            );
            $st->execute([$candidate]);
            if ($st->fetch()) continue;

            try {
                $email = filter_var($emails[$generated] ?? '', FILTER_VALIDATE_EMAIL) ?: null;
                $ins->execute([$gameId, $candidate, $email]);
                $pins[] = $candidate;
                $generated++;
            } catch (\Exception $e) {
                // Race condition muy poco probable — simplemente reintentamos
            }
        }

        return $pins;
    }

    // ── Consultas por PIN ─────────────────────────────

    /**
     * Busca una partida activa por PIN individual (no usado y no finalizada).
     * Incluye el email del jugador asignado a ese PIN.
     */
    public function getByIndividualPin(string $pin): ?array {
        $st = $this->db->prepare(
            "SELECT g.*, ip.email AS player_email
             FROM individual_pins ip
             JOIN games g ON ip.game_id = g.id
             WHERE ip.pin=? AND ip.used=0 AND g.status!='finished'"
        );
        $st->execute([$pin]);
        return $st->fetch() ?: null;
    }

    /** Marca un PIN individual como usado y lo vincula al jugador que se unió */
    public function claimIndividualPin(string $pin, int $playerId): void {
        $this->db->prepare(
            "UPDATE individual_pins SET used=1, player_id=? WHERE pin=?"
        )->execute([$playerId, $pin]);
    }

    /** Busca una partida activa por el PIN compartido de sala */
    public function getByPin(string $pin): ?array {
        $st = $this->db->prepare("SELECT * FROM games WHERE pin=? AND status!='finished'");
        $st->execute([$pin]);
        return $st->fetch() ?: null;
    }

    /** Busca una partida por su ID interno */
    public function getById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM games WHERE id=?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Verifica que el token de admin sea válido para esa partida */
    public function verifyAdmin(int $id, string $token): bool {
        $game = $this->getById($id);
        return $game && hash_equals($game['admin_token'], $token);
    }

    // ── Control de partida ────────────────────────────

    /**
     * Inicia la partida: asigna una canción ancla a cada jugador como punto
     * de partida de su línea del tiempo, y pasa el estado a 'question' ronda 1.
     *
     * La canción ancla es diferente a las que se usarán en las rondas para
     * que no se repitan. Se respeta el género seleccionado si no es 'Todos'.
     */
    public function start(int $gameId): void {
        $game          = $this->getById($gameId);
        $gameType      = $game['game_type'] ?? 'song';
        $selectedGenre = $game['selected_genre'] ?: 'Todos';

        $contentTable  = $gameType === 'meme' ? 'memes' : 'songs';
        $gameTable     = $gameType === 'meme' ? 'game_memes' : 'game_songs';
        $timelineTable = $gameType === 'meme' ? 'player_meme_timeline' : 'player_timeline';
        $itemCol       = $gameType === 'meme' ? 'meme_id' : 'song_id';

        // Contenido reservado para las rondas (no puede usarse como ancla)
        $st = $this->db->prepare("SELECT {$itemCol} FROM {$gameTable} WHERE game_id=?");
        $st->execute([$gameId]);
        $roundIds = $st->fetchAll(PDO::FETCH_COLUMN);

        // Lista de jugadores que necesitan elemento ancla
        $st = $this->db->prepare("SELECT id FROM players WHERE game_id=?");
        $st->execute([$gameId]);
        $playerIds = $st->fetchAll(PDO::FETCH_COLUMN);

        $insTimeline = $this->db->prepare(
            "INSERT IGNORE INTO {$timelineTable} (player_id, game_id, {$itemCol}) VALUES (?,?,?)"
        );

        // Placeholder SQL para excluir el contenido de rondas
        $placeholders = $roundIds ? implode(',', array_fill(0, count($roundIds), '?')) : '0';

        foreach ($playerIds as $pid) {
            if ($selectedGenre !== 'Todos') {
                // Intentar ancla del mismo género que no esté en las rondas
                $st = $this->db->prepare(
                    "SELECT id FROM {$contentTable} WHERE genre=? AND id NOT IN ($placeholders) ORDER BY RAND() LIMIT 1"
                );
                $st->execute(array_merge([$selectedGenre], $roundIds));
                $initial = $st->fetchColumn();

                // Fallback: cualquier elemento del género aunque esté en rondas
                if (!$initial) {
                    $st = $this->db->prepare("SELECT id FROM {$contentTable} WHERE genre=? ORDER BY RAND() LIMIT 1");
                    $st->execute([$selectedGenre]);
                    $initial = $st->fetchColumn();
                }
            } else {
                // Sin filtro de género: cualquier elemento fuera de rondas
                $st = $this->db->prepare(
                    "SELECT id FROM {$contentTable} WHERE id NOT IN ($placeholders) ORDER BY RAND() LIMIT 1"
                );
                $st->execute($roundIds);
                $initial = $st->fetchColumn();
            }

            // Último fallback: cualquier elemento de la tabla
            if (!$initial) {
                $st = $this->db->prepare("SELECT id FROM {$contentTable} ORDER BY RAND() LIMIT 1");
                $st->execute();
                $initial = $st->fetchColumn();
            }

            if ($initial) $insTimeline->execute([$pid, $gameId, $initial]);
        }

        // Pasar a estado 'question' ronda 1 y registrar el momento de inicio (UTC)
        $this->db->prepare(
            "UPDATE games SET status='question', current_round=1, question_started_at=UTC_TIMESTAMP() WHERE id=?"
        )->execute([$gameId]);
    }

    /** Pasa la partida a estado 'results' para mostrar el año de la canción */
    public function showResults(int $gameId): void {
        $this->db->prepare("UPDATE games SET status='results' WHERE id=?")->execute([$gameId]);
    }

    /**
     * Avanza a la siguiente ronda o finaliza la partida si era la última.
     * Resetea la racha a 0 para los jugadores que no respondieron en la ronda completada
     * (no tienen entrada en answers para la canción de esa ronda).
     * @return string  Nuevo estado: 'question' o 'finished'
     */
    public function nextRound(int $gameId): string {
        $game         = $this->getById($gameId);
        $gameType     = $game['game_type'] ?? 'song';
        $currentRound = (int)$game['current_round'];
        $next         = $currentRound + 1;

        $answersTable = $gameType === 'meme' ? 'meme_answers' : 'answers';
        $gameTable    = $gameType === 'meme' ? 'game_memes' : 'game_songs';
        $itemCol      = $gameType === 'meme' ? 'meme_id' : 'song_id';

        // Romper la racha de jugadores que no respondieron en la ronda completada
        $this->db->prepare(
            "UPDATE players SET streak = 0
             WHERE game_id = ?
               AND id NOT IN (
                 SELECT player_id FROM {$answersTable}
                 WHERE game_id = ? AND {$itemCol} = (
                   SELECT {$itemCol} FROM {$gameTable}
                   WHERE game_id = ? AND round_number = ?
                 )
               )"
        )->execute([$gameId, $gameId, $gameId, $currentRound]);

        if ($next > (int)$game['total_rounds']) {
            $this->db->prepare("UPDATE games SET status='finished' WHERE id=?")->execute([$gameId]);
            return 'finished';
        }

        $this->db->prepare(
            "UPDATE games SET status='question', current_round=?, question_started_at=UTC_TIMESTAMP() WHERE id=?"
        )->execute([$next, $gameId]);
        return 'question';
    }

    /** Devuelve los datos de la canción (o meme) de la ronda actual */
    public function getCurrentSong(int $gameId): ?array {
        $game     = $this->getById($gameId);
        $gameType = $game['game_type'] ?? 'song';

        if ($gameType === 'meme') {
            $st = $this->db->prepare(
                "SELECT m.id, m.image_url, m.title, m.year, m.genre, gm.round_number
                 FROM game_memes gm
                 JOIN memes m ON gm.meme_id = m.id
                 JOIN games  g ON gm.game_id = g.id
                 WHERE gm.game_id=? AND gm.round_number=g.current_round"
            );
        } else {
            $st = $this->db->prepare(
                "SELECT s.id, s.title, s.artist, s.year, s.genre,
                        s.spotify_url, s.youtube_url, s.artwork_url, gs.round_number
                 FROM game_songs gs
                 JOIN songs s ON gs.song_id = s.id
                 JOIN games  g ON gs.game_id = g.id
                 WHERE gs.game_id=? AND gs.round_number=g.current_round"
            );
        }
        $st->execute([$gameId]);
        return $st->fetch() ?: null;
    }

    // ── Estado en tiempo real ─────────────────────────

    /**
     * Devuelve el estado actual de la partida para el polling del frontend.
     * Si el tiempo de pregunta ha expirado, hace la transición automática
     * a 'results' sin necesidad de que el admin pulse el botón.
     */
    public function getState(int $gameId): array {
        $game = $this->getById($gameId);

        // Auto-transición cuando se acaba el tiempo de respuesta
        if ($game && $game['status'] === 'question' && $game['question_started_at']) {
            $elapsed = time() - strtotime($game['question_started_at'] . ' UTC');
            if ($elapsed >= (int)$game['question_time']) {
                $this->db->prepare("UPDATE games SET status='results' WHERE id=?")->execute([$gameId]);
                $game = $this->getById($gameId);
            }
        }

        if (!$game) return ['error' => 'Partida no encontrada'];

        // Calcular segundos restantes para la cuenta atrás del frontend
        $timeLeft = (int)$game['question_time'];
        if ($game['status'] === 'question' && $game['question_started_at']) {
            $elapsed  = time() - strtotime($game['question_started_at'] . ' UTC');
            $timeLeft = max(0, (int)$game['question_time'] - $elapsed);
        }

        return [
            'status'        => $game['status'],
            'current_round' => (int)$game['current_round'],
            'total_rounds'  => (int)$game['total_rounds'],
            'question_time' => (int)$game['question_time'],
            'time_left'     => $timeLeft,
            'show_links'    => (int)($game['show_links']    ?? 0),
            'embed_youtube' => (int)($game['embed_youtube'] ?? 0),
            'autoplay'      => (int)($game['autoplay']      ?? 0),
            'hard_mode'     => (int)($game['hard_mode']     ?? 0),
            'pin_mode'      => $game['pin_mode'] ?? 'shared',
            'prize_1'       => $game['prize_1'] ?? null,
            'prize_2'       => $game['prize_2'] ?? null,
            'prize_3'       => $game['prize_3'] ?? null,
            'game_type'     => $game['game_type'] ?? 'song',
            'selected_genre' => $game['selected_genre'] ?? 'Todos',
            'is_public'      => (int)($game['is_public'] ?? 0),
        ];
    }

    /**
     * Lista las partidas públicas abiertas (en sala de espera) para el
     * navegador de servidores del jugador.
     */
    public function listPublic(): array {
        $st = $this->db->query(
            "SELECT g.id, g.pin, g.selected_genre, g.game_type, g.total_rounds,
                    g.organizer_email, COUNT(p.id) AS player_count
             FROM games g
             LEFT JOIN players p ON p.game_id = g.id
             WHERE g.is_public = 1 AND g.status = 'waiting'
             GROUP BY g.id
             ORDER BY g.created_at DESC
             LIMIT 50"
        );
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
