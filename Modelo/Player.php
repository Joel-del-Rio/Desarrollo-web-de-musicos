<?php
require_once __DIR__ . '/Database.php';

class Player {
    private PDO $db;
    private const COLORS = ['#e94560','#4ECDC4','#45B7D1','#96CEB4','#FFEAA7','#DDA0DD','#FF6B35'];

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    public function create(int $gameId, string $name): array {
        $color = self::COLORS[random_int(0, count(self::COLORS) - 1)];
        $st = $this->db->prepare(
            "INSERT INTO players (game_id, name, avatar_color) VALUES (?, ?, ?)"
        );
        $st->execute([$gameId, $name, $color]);
        return ['id' => (int)$this->db->lastInsertId(), 'name' => $name, 'color' => $color];
    }

    public function getById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM players WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function getByGame(int $gameId): array {
        $st = $this->db->prepare(
            "SELECT id, name, score, avatar_color FROM players WHERE game_id = ? ORDER BY score DESC, name ASC"
        );
        $st->execute([$gameId]);
        return $st->fetchAll();
    }

    public function ping(int $playerId): void {
        $this->db->prepare("UPDATE players SET last_seen=NOW() WHERE id=?")->execute([$playerId]);
    }

    public function hasAnswered(int $playerId, int $gameId, int $songId): bool {
        $st = $this->db->prepare(
            "SELECT 1 FROM answers WHERE player_id=? AND game_id=? AND song_id=?"
        );
        $st->execute([$playerId, $gameId, $songId]);
        return (bool)$st->fetch();
    }

    public function submitAnswer(int $playerId, int $gameId, int $songId, int $yearGuess, int $correctYear, int $timeLeft): array {
        $correct = ($yearGuess === $correctYear);
        $points  = $correct ? max(100, 1000 - ((20 - $timeLeft) * 40)) : 0;

        $st = $this->db->prepare(
            "INSERT IGNORE INTO answers (game_id, player_id, song_id, year_guess, is_correct, points_earned)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $st->execute([$gameId, $playerId, $songId, $yearGuess, $correct ? 1 : 0, $points]);

        if ($correct && $st->rowCount() > 0) {
            $this->db->prepare("UPDATE players SET score = score + ? WHERE id=?")->execute([$points, $playerId]);
        }

        return ['correct' => $correct, 'points' => $points];
    }

    public function getRoundResults(int $gameId, int $songId): array {
        $st = $this->db->prepare(
            "SELECT p.name, p.avatar_color, a.year_guess, a.is_correct, a.points_earned
             FROM answers a
             JOIN players p ON a.player_id = p.id
             WHERE a.game_id=? AND a.song_id=?
             ORDER BY a.points_earned DESC, a.answered_at ASC"
        );
        $st->execute([$gameId, $songId]);
        return $st->fetchAll();
    }

    public function getAnswerCount(int $gameId, int $songId): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM answers WHERE game_id=? AND song_id=?");
        $st->execute([$gameId, $songId]);
        return (int)$st->fetchColumn();
    }
}
