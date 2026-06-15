<?php
require_once __DIR__ . '/Database.php';

class Game {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    public function create(
        int $totalRounds, int $questionTime, string $genre = 'Todos',
        int $showLinks = 0, int $embedYoutube = 0, int $autoplay = 0,
        string $pinMode = 'shared', string $organizerEmail = '',
        int $individualCount = 0,
        string $prize1 = '', string $prize2 = '', string $prize3 = '',
        array $playerEmails = []
    ): array {
        // PIN único entre partidas activas
        do {
            $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $st  = $this->db->prepare("SELECT id FROM games WHERE pin=? AND status!='finished'");
            $st->execute([$pin]);
        } while ($st->fetch());

        $token = bin2hex(random_bytes(32));
        $this->db->prepare(
            "INSERT INTO games (pin, admin_token, total_rounds, question_time, selected_genre, show_links, embed_youtube, autoplay, pin_mode, organizer_email, prize_1, prize_2, prize_3)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([$pin, $token, $totalRounds, $questionTime, $genre, $showLinks, $embedYoutube, $autoplay, $pinMode, $organizerEmail ?: null, $prize1 ?: null, $prize2 ?: null, $prize3 ?: null]);
        $gameId = (int)$this->db->lastInsertId();

        // Seleccionar canciones para las rondas (filtradas por género si aplica)
        if ($genre === 'Todos') {
            $st = $this->db->prepare("SELECT id FROM songs ORDER BY RAND() LIMIT ?");
            $st->execute([$totalRounds]);
        } else {
            $st = $this->db->prepare("SELECT id FROM songs WHERE genre=? ORDER BY RAND() LIMIT ?");
            $st->execute([$genre, $totalRounds]);
        }
        $songs = $st->fetchAll(PDO::FETCH_COLUMN);

        $ins = $this->db->prepare(
            "INSERT INTO game_songs (game_id, song_id, round_number) VALUES (?,?,?)"
        );
        foreach ($songs as $i => $songId) {
            $ins->execute([$gameId, $songId, $i + 1]);
        }

        $result = ['id' => $gameId, 'pin' => $pin, 'admin_token' => $token, 'pin_mode' => $pinMode];

        if ($pinMode === 'individual' && $individualCount > 0) {
            $result['individual_pins'] = $this->generateIndividualPins($gameId, $individualCount, $playerEmails);
        }

        return $result;
    }

    private function generateIndividualPins(int $gameId, int $count, array $emails = []): array {
        $pins      = [];
        $ins       = $this->db->prepare("INSERT INTO individual_pins (game_id, pin, email) VALUES (?,?,?)");
        $generated = 0;
        $attempts  = 0;

        while ($generated < $count && $attempts < $count * 20) {
            $attempts++;
            $candidate = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $st = $this->db->prepare("SELECT 1 FROM games WHERE pin=? AND status!='finished'");
            $st->execute([$candidate]);
            if ($st->fetch()) continue;

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
            } catch (\Exception $e) { /* race condition, skip */ }
        }

        return $pins;
    }

    public function getByIndividualPin(string $pin): ?array {
        $st = $this->db->prepare(
            "SELECT g.*, ip.email AS player_email FROM individual_pins ip
             JOIN games g ON ip.game_id=g.id
             WHERE ip.pin=? AND ip.used=0 AND g.status!='finished'"
        );
        $st->execute([$pin]);
        return $st->fetch() ?: null;
    }

    public function claimIndividualPin(string $pin, int $playerId): void {
        $this->db->prepare(
            "UPDATE individual_pins SET used=1, player_id=? WHERE pin=?"
        )->execute([$playerId, $pin]);
    }

    public function getByPin(string $pin): ?array {
        $st = $this->db->prepare("SELECT * FROM games WHERE pin=? AND status!='finished'");
        $st->execute([$pin]);
        return $st->fetch() ?: null;
    }

    public function getById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM games WHERE id=?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function verifyAdmin(int $id, string $token): bool {
        $game = $this->getById($id);
        return $game && hash_equals($game['admin_token'], $token);
    }

    /**
     * Inicia la partida: reparte la canción inicial a cada jugador y pasa a ronda 1.
     */
    public function start(int $gameId): void {
        // IDs de canciones reservadas para las rondas
        $st = $this->db->prepare("SELECT song_id FROM game_songs WHERE game_id=?");
        $st->execute([$gameId]);
        $roundIds = $st->fetchAll(PDO::FETCH_COLUMN);

        // Jugadores
        $st = $this->db->prepare("SELECT id FROM players WHERE game_id=?");
        $st->execute([$gameId]);
        $playerIds = $st->fetchAll(PDO::FETCH_COLUMN);

        $insTimeline = $this->db->prepare(
            "INSERT IGNORE INTO player_timeline (player_id, game_id, song_id) VALUES (?,?,?)"
        );

        // Obtener el género seleccionado para filtrar la canción ancla
        $stGame = $this->db->prepare("SELECT selected_genre FROM games WHERE id=?");
        $stGame->execute([$gameId]);
        $selectedGenre = $stGame->fetchColumn() ?: 'Todos';

        $placeholders = $roundIds ? implode(',', array_fill(0, count($roundIds), '?')) : '0';
        foreach ($playerIds as $pid) {
            // Canción ancla: del mismo género (si no es "Todos") y no usada en rondas
            if ($selectedGenre !== 'Todos') {
                $st = $this->db->prepare(
                    "SELECT id FROM songs WHERE genre=? AND id NOT IN ($placeholders) ORDER BY RAND() LIMIT 1"
                );
                $st->execute(array_merge([$selectedGenre], $roundIds));
                $initial = $st->fetchColumn();
                // Fallback: del género pero sin filtrar rondas
                if (!$initial) {
                    $st = $this->db->prepare("SELECT id FROM songs WHERE genre=? ORDER BY RAND() LIMIT 1");
                    $st->execute([$selectedGenre]);
                    $initial = $st->fetchColumn();
                }
            } else {
                $st = $this->db->prepare(
                    "SELECT id FROM songs WHERE id NOT IN ($placeholders) ORDER BY RAND() LIMIT 1"
                );
                $st->execute($roundIds);
                $initial = $st->fetchColumn();
            }

            if (!$initial) { // Fallback final: cualquier canción
                $st = $this->db->prepare("SELECT id FROM songs ORDER BY RAND() LIMIT 1");
                $st->execute();
                $initial = $st->fetchColumn();
            }
            if ($initial) $insTimeline->execute([$pid, $gameId, $initial]);
        }

        $this->db->prepare(
            "UPDATE games SET status='question', current_round=1, question_started_at=NOW() WHERE id=?"
        )->execute([$gameId]);
    }

    public function showResults(int $gameId): void {
        $this->db->prepare("UPDATE games SET status='results' WHERE id=?")->execute([$gameId]);
    }

    public function nextRound(int $gameId): string {
        $game = $this->getById($gameId);
        $next = (int)$game['current_round'] + 1;
        if ($next > (int)$game['total_rounds']) {
            $this->db->prepare("UPDATE games SET status='finished' WHERE id=?")->execute([$gameId]);
            return 'finished';
        }
        $this->db->prepare(
            "UPDATE games SET status='question', current_round=?, question_started_at=NOW() WHERE id=?"
        )->execute([$next, $gameId]);
        return 'question';
    }

    public function getCurrentSong(int $gameId): ?array {
        $st = $this->db->prepare(
            "SELECT s.id, s.title, s.artist, s.year, s.genre, s.spotify_url, s.youtube_url, gs.round_number
             FROM game_songs gs
             JOIN songs s ON gs.song_id = s.id
             JOIN games  g ON gs.game_id = g.id
             WHERE gs.game_id=? AND gs.round_number=g.current_round"
        );
        $st->execute([$gameId]);
        return $st->fetch() ?: null;
    }

    public function getState(int $gameId): array {
        // Auto-transición cuando se acaba el tiempo
        $game = $this->getById($gameId);
        if ($game && $game['status'] === 'question' && $game['question_started_at']) {
            $elapsed = time() - strtotime($game['question_started_at']);
            if ($elapsed >= (int)$game['question_time']) {
                $this->db->prepare("UPDATE games SET status='results' WHERE id=?")->execute([$gameId]);
                $game = $this->getById($gameId);
            }
        }
        if (!$game) return ['error' => 'Partida no encontrada'];

        $timeLeft = (int)$game['question_time'];
        if ($game['status'] === 'question' && $game['question_started_at']) {
            $elapsed  = time() - strtotime($game['question_started_at']);
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
            'pin_mode'      => $game['pin_mode'] ?? 'shared',
            'prize_1'       => $game['prize_1'] ?? null,
            'prize_2'       => $game['prize_2'] ?? null,
            'prize_3'       => $game['prize_3'] ?? null,
        ];
    }
}
