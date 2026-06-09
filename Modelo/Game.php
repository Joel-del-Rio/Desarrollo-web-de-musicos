<?php
require_once __DIR__ . '/Database.php';

class Game {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    public function create(int $totalRounds, int $questionTime, string $genre = 'Todos'): array {
        // PIN único entre partidas activas
        do {
            $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $st  = $this->db->prepare("SELECT id FROM games WHERE pin=? AND status!='finished'");
            $st->execute([$pin]);
        } while ($st->fetch());

        $token = bin2hex(random_bytes(32));
        $this->db->prepare(
            "INSERT INTO games (pin, admin_token, total_rounds, question_time, selected_genre) VALUES (?,?,?,?,?)"
        )->execute([$pin, $token, $totalRounds, $questionTime, $genre]);
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

        return ['id' => $gameId, 'pin' => $pin, 'admin_token' => $token];
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

        $placeholders = $roundIds ? implode(',', array_fill(0, count($roundIds), '?')) : '0';
        foreach ($playerIds as $pid) {
            // Canción inicial: una NOT usada en rondas, si es posible
            $st = $this->db->prepare(
                "SELECT id FROM songs WHERE id NOT IN ($placeholders) ORDER BY RAND() LIMIT 1"
            );
            $st->execute($roundIds);
            $initial = $st->fetchColumn();

            if (!$initial) { // Fallback: cualquier canción
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
            "SELECT s.id, s.title, s.artist, s.year, s.genre, gs.round_number
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
        ];
    }
}
