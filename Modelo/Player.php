<?php
require_once __DIR__ . '/Database.php';

class Player {
    private PDO $db;
    private const COLORS = ['#e94560','#4ECDC4','#45B7D1','#FF6B35','#DDA0DD','#2ecc71','#f39c12'];

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    public function create(int $gameId, string $name, string $email = ''): array {
        $color = self::COLORS[random_int(0, count(self::COLORS) - 1)];
        $this->db->prepare(
            "INSERT INTO players (game_id, name, avatar_color, email) VALUES (?,?,?,?)"
        )->execute([$gameId, $name, $color, $email ?: null]);
        return ['id' => (int)$this->db->lastInsertId(), 'name' => $name, 'color' => $color];
    }

    public function getById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM players WHERE id=?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function getByGame(int $gameId): array {
        $st = $this->db->prepare(
            "SELECT id, name, score, avatar_color FROM players WHERE game_id=? ORDER BY score DESC, name ASC"
        );
        $st->execute([$gameId]);
        return $st->fetchAll();
    }

    public function ping(int $playerId): void {
        $this->db->prepare("UPDATE players SET last_seen=NOW() WHERE id=?")->execute([$playerId]);
    }

    /** Devuelve el timeline del jugador ordenado cronológicamente */
    public function getTimeline(int $playerId, int $gameId): array {
        $st = $this->db->prepare(
            "SELECT s.id, s.title, s.artist, s.year, s.genre
             FROM player_timeline pt
             JOIN songs s ON pt.song_id = s.id
             WHERE pt.player_id=? AND pt.game_id=?
             ORDER BY s.year ASC, s.title ASC"
        );
        $st->execute([$playerId, $gameId]);
        return $st->fetchAll();
    }

    public function hasAnswered(int $playerId, int $gameId, int $songId): bool {
        $st = $this->db->prepare(
            "SELECT 1 FROM answers WHERE player_id=? AND game_id=? AND song_id=?"
        );
        $st->execute([$playerId, $gameId, $songId]);
        return (bool)$st->fetch();
    }

    /**
     * Evalúa si la posición elegida es cronológicamente correcta en el timeline del jugador.
     * position=0 → antes de todo, position=N → después de todo.
     */
    public function submitPositionAnswer(
        int $playerId, int $gameId, int $songId,
        int $position, int $songYear,
        int $timeLeft, int $questionTime
    ): array {
        $timeline = $this->getTimeline($playerId, $gameId);
        $years    = array_column($timeline, 'year');

        $isCorrect = $this->isPositionCorrect($years, $position, $songYear);
        $points    = 0;
        if ($isCorrect) {
            $points = 500 + (int)round(500 * ($timeLeft / max(1, $questionTime)));
        }

        $st = $this->db->prepare(
            "INSERT IGNORE INTO answers (game_id, player_id, song_id, position_guess, is_correct, points_earned)
             VALUES (?,?,?,?,?,?)"
        );
        $st->execute([$gameId, $playerId, $songId, $position, $isCorrect ? 1 : 0, $points]);

        if ($isCorrect && $this->db->lastInsertId() > 0) {
            // Añadir al timeline
            $this->db->prepare(
                "INSERT IGNORE INTO player_timeline (player_id, game_id, song_id) VALUES (?,?,?)"
            )->execute([$playerId, $gameId, $songId]);
            // Sumar puntos en partida
            $this->db->prepare("UPDATE players SET score=score+? WHERE id=?")->execute([$points, $playerId]);
            // Acumular en puntuación global solo en partidas de PIN individual
            $gmSt = $this->db->prepare("SELECT pin_mode FROM games WHERE id=?");
            $gmSt->execute([$gameId]);
            $gameRow = $gmSt->fetch();
            if (($gameRow['pin_mode'] ?? '') === 'individual') {
                $pl = $this->getById($playerId);
                if (!empty($pl['email'])) {
                    $this->db->prepare(
                        "INSERT INTO global_scores (email, name, total_points)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE total_points = total_points + ?, name = VALUES(name)"
                    )->execute([$pl['email'], $pl['name'], $points, $points]);
                }
            }
        }

        return ['correct' => $isCorrect, 'points' => $points];
    }

    public function getAnswerCount(int $gameId, int $songId): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM answers WHERE game_id=? AND song_id=?");
        $st->execute([$gameId, $songId]);
        return (int)$st->fetchColumn();
    }

    public function getRoundResults(int $gameId, int $songId): array {
        $st = $this->db->prepare(
            "SELECT p.name, p.avatar_color, a.position_guess, a.is_correct, a.points_earned
             FROM answers a
             JOIN players p ON a.player_id=p.id
             WHERE a.game_id=? AND a.song_id=?
             ORDER BY a.points_earned DESC, a.answered_at ASC"
        );
        $st->execute([$gameId, $songId]);
        return $st->fetchAll();
    }

    /** Posición correcta: newYear debe quedar entre prev y next en el timeline ordenado */
    private function isPositionCorrect(array $years, int $position, int $newYear): bool {
        sort($years);
        $n    = count($years);
        $prev = $position > 0 ? $years[$position - 1] : 0;
        $next = $position < $n ? $years[$position]    : PHP_INT_MAX;
        return $newYear >= $prev && $newYear <= $next;
    }
}
