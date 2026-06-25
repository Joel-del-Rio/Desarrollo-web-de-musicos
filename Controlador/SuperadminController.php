<?php
/**
 * SuperadminController.php — Panel de supervisión global
 *
 * Acceso con las mismas credenciales que el panel de premios.
 * Devuelve datos de todas las partidas, jugadores y estadísticas.
 */
require_once __DIR__ . '/../Modelo/Database.php';

class SuperadminController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    /** Verifica credenciales (mismas que panel de premios) */
    public function login(): array {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';
        if ($email === 'joel@nite.black' && hash('sha256', $pass) === '4f1cf128cc1cda92976abb1be3455ace44aa9b5b4a3459ca5f89c0657536cc40') {
            return ['success' => true];
        }
        return ['error' => 'Credenciales incorrectas'];
    }

    /** Estadísticas globales del sistema */
    public function getStats(): array {
        $stats = [];

        $stats['total_games']   = (int)$this->db->query("SELECT COUNT(*) FROM games")->fetchColumn();
        $stats['active_games']  = (int)$this->db->query("SELECT COUNT(*) FROM games WHERE status != 'finished'")->fetchColumn();
        $stats['total_players'] = (int)$this->db->query("SELECT COUNT(*) FROM players")->fetchColumn();
        $stats['total_answers'] = (int)$this->db->query("SELECT COUNT(*) FROM answers")->fetchColumn();
        $stats['correct_answers'] = (int)$this->db->query("SELECT COUNT(*) FROM answers WHERE is_correct=1")->fetchColumn();

        $topGenre = $this->db->query(
            "SELECT selected_genre, COUNT(*) c FROM games GROUP BY selected_genre ORDER BY c DESC LIMIT 1"
        )->fetch();
        $stats['top_genre'] = $topGenre ? $topGenre['selected_genre'] : '—';

        $stats['avg_players'] = round(
            $this->db->query("SELECT AVG(cnt) FROM (SELECT COUNT(*) cnt FROM players GROUP BY game_id) t")->fetchColumn() ?? 0, 1
        );

        return ['success' => true, 'stats' => $stats];
    }

    /** Finaliza automáticamente partidas caducadas */
    private function finishStale(): void {
        // Partidas atascadas en resultados → finalizadas
        $this->db->exec("UPDATE games SET status='finished' WHERE status='results'");

        // Partidas en pregunta desde hace más de 1 hora → finalizadas
        $this->db->exec("
            UPDATE games SET status='finished'
            WHERE status='question'
              AND question_started_at IS NOT NULL
              AND TIMESTAMPDIFF(SECOND, question_started_at, UTC_TIMESTAMP()) > 3600
        ");

        // Partidas esperando jugadores desde hace más de 1 hora → finalizadas
        $this->db->exec("
            UPDATE games SET status='finished'
            WHERE status='waiting'
              AND TIMESTAMPDIFF(SECOND, created_at, UTC_TIMESTAMP()) > 3600
        ");
    }

    /** Lista todas las partidas con datos agregados */
    public function getGames(): array {
        $this->finishStale();

        $rows = $this->db->query("
            SELECT g.id, g.pin, g.status, g.selected_genre, g.current_round, g.total_rounds,
                   g.pin_mode, g.organizer_email, g.created_at,
                   COUNT(DISTINCT p.id) AS player_count,
                   MAX(p.score) AS top_score
            FROM games g
            LEFT JOIN players p ON p.game_id = g.id
            GROUP BY g.id
            ORDER BY g.id DESC
            LIMIT 200
        ")->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'games' => $rows];
    }

    /** Reinicia puntos de todos los jugadores (partidas y ranking global) */
    public function resetPoints(): array {
        $this->db->exec("UPDATE players SET score=0, streak=0");
        $this->db->exec("UPDATE global_players SET total_points=0");
        return ['success' => true];
    }

    /** Detalle de una partida: jugadores + canciones jugadas */
    public function getGameDetail(): array {
        $gameId = (int)($_GET['game_id'] ?? 0);
        if (!$gameId) return ['error' => 'game_id requerido'];

        $game = $this->db->prepare("SELECT * FROM games WHERE id=?");
        $game->execute([$gameId]);
        $gameData = $game->fetch(PDO::FETCH_ASSOC);
        if (!$gameData) return ['error' => 'Partida no encontrada'];

        $players = $this->db->prepare("
            SELECT id, name, score, streak, email, avatar_color, joined_at
            FROM players WHERE game_id=? ORDER BY score DESC
        ");
        $players->execute([$gameId]);

        $songs = $this->db->prepare("
            SELECT gs.round_number, s.title, s.artist, s.year, s.genre
            FROM game_songs gs
            JOIN songs s ON s.id = gs.song_id
            WHERE gs.game_id = ?
            ORDER BY gs.round_number ASC
        ");
        $songs->execute([$gameId]);

        return [
            'success' => true,
            'game'    => $gameData,
            'players' => $players->fetchAll(PDO::FETCH_ASSOC),
            'songs'   => $songs->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
