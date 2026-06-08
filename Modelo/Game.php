<?php
require_once __DIR__ . '/Database.php';

class Game {
    private PDO $db;
    private const QUESTION_SECONDS = 20;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    public function create(int $totalRounds): array {
        // PIN único para partidas activas
        do {
            $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $st  = $this->db->prepare("SELECT id FROM games WHERE pin = ? AND status != 'finished'");
            $st->execute([$pin]);
        } while ($st->fetch());

        $token = bin2hex(random_bytes(32));
        $st = $this->db->prepare(
            "INSERT INTO games (pin, admin_token, total_rounds) VALUES (?, ?, ?)"
        );
        $st->execute([$pin, $token, $totalRounds]);
        $gameId = (int)$this->db->lastInsertId();

        // Seleccionar canciones aleatorias y guardar opciones de respuesta
        $st = $this->db->prepare("SELECT id, year FROM songs ORDER BY RAND() LIMIT ?");
        $st->execute([$totalRounds]);
        $songs = $st->fetchAll();

        $ins = $this->db->prepare(
            "INSERT INTO game_songs (game_id, song_id, round_number, options) VALUES (?, ?, ?, ?)"
        );
        foreach ($songs as $i => $song) {
            $options = $this->buildOptions((int)$song['year'], $gameId * 100 + $i);
            $ins->execute([$gameId, $song['id'], $i + 1, json_encode($options)]);
        }

        return ['id' => $gameId, 'pin' => $pin, 'admin_token' => $token];
    }

    public function getByPin(string $pin): ?array {
        $st = $this->db->prepare("SELECT * FROM games WHERE pin = ? AND status != 'finished'");
        $st->execute([$pin]);
        return $st->fetch() ?: null;
    }

    public function getById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM games WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function verifyAdmin(int $id, string $token): bool {
        $game = $this->getById($id);
        return $game && hash_equals($game['admin_token'], $token);
    }

    public function start(int $gameId): void {
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
            "SELECT s.id, s.title, s.artist, s.year, s.genre, gs.round_number, gs.options
             FROM game_songs gs
             JOIN songs s ON gs.song_id = s.id
             JOIN games  g ON gs.game_id = g.id
             WHERE gs.game_id = ? AND gs.round_number = g.current_round"
        );
        $st->execute([$gameId]);
        $row = $st->fetch();
        if ($row) {
            $row['options'] = json_decode($row['options'], true);
        }
        return $row ?: null;
    }

    public function getState(int $gameId): array {
        // Auto-transición por tiempo
        $this->db->prepare(
            "UPDATE games SET status='results'
             WHERE id=? AND status='question'
             AND TIMESTAMPDIFF(SECOND, question_started_at, NOW()) >= ?"
        )->execute([$gameId, self::QUESTION_SECONDS]);

        $game = $this->getById($gameId);
        if (!$game) return ['error' => 'Partida no encontrada'];

        $timeLeft = self::QUESTION_SECONDS;
        if ($game['status'] === 'question' && $game['question_started_at']) {
            $elapsed  = time() - strtotime($game['question_started_at']);
            $timeLeft = max(0, self::QUESTION_SECONDS - $elapsed);
        }

        return [
            'status'        => $game['status'],
            'current_round' => (int)$game['current_round'],
            'total_rounds'  => (int)$game['total_rounds'],
            'time_left'     => $timeLeft,
        ];
    }

    // Genera 4 opciones de año deterministas a partir de un seed
    private function buildOptions(int $correctYear, int $seed): array {
        $rng = static function () use (&$seed): int {
            $seed = ($seed * 1664525 + 1013904223) & 0x7FFFFFFF;
            return $seed;
        };

        $options = [$correctYear];
        $tries   = 0;
        while (count($options) < 4 && $tries < 200) {
            $tries++;
            $offset = ($rng() % 17) - 8; // -8 … +8, never 0
            if ($offset === 0) $offset = 1;
            $y = max(1950, min(2024, $correctYear + $offset));
            if (!in_array($y, $options, true)) {
                $options[] = $y;
            }
        }

        // Fisher-Yates determinista
        for ($i = count($options) - 1; $i > 0; $i--) {
            $j = $rng() % ($i + 1);
            [$options[$i], $options[$j]] = [$options[$j], $options[$i]];
        }

        return $options;
    }
}
