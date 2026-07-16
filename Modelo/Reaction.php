<?php
/**
 * Reaction.php — Reacciones tipo Kahoot durante la partida
 *
 * Cualquier jugador puede lanzar un emoji de una lista fija; el resto de
 * jugadores de la misma partida lo ven al momento a través del polling
 * normal de player_state (sin canal nuevo, solo un id incremental).
 */
require_once __DIR__ . '/Database.php';

class Reaction {
    private PDO $db;

    // Lista fija de reacciones permitidas — igual en el frontend para pintar los botones
    public const EMOJIS = ['👍', '❤️', '😂', '😮', '🔥', '👏'];

    // Tiempo mínimo entre dos reacciones del mismo jugador (evita spam)
    private const COOLDOWN_SECONDS = 5;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    /**
     * Registra una reacción de un jugador, respetando el cooldown entre envíos.
     * Se valida también en el servidor (no solo en el frontend) para que no se
     * pueda saltar llamando directamente a la API.
     */
    public function send(int $gameId, int $playerId, string $emoji): array {
        if (!in_array($emoji, self::EMOJIS, true)) return ['error' => 'Reacción no válida'];

        $st = $this->db->prepare("SELECT created_at FROM reactions WHERE player_id=? ORDER BY id DESC LIMIT 1");
        $st->execute([$playerId]);
        $lastAt = $st->fetchColumn();
        if ($lastAt && (time() - strtotime($lastAt . ' UTC')) < self::COOLDOWN_SECONDS) {
            return ['error' => 'Espera unos segundos antes de reaccionar otra vez'];
        }

        $this->db->prepare(
            "INSERT INTO reactions (game_id, player_id, emoji) VALUES (?,?,?)"
        )->execute([$gameId, $playerId, $emoji]);

        return ['success' => true, 'id' => (int)$this->db->lastInsertId()];
    }

    /**
     * Devuelve las reacciones de la partida posteriores a $sinceId (para que
     * cada jugador solo reciba las que aún no ha visto), con el nombre de
     * quien la lanzó. Limitado a las últimas 30 para no sobrecargar el poll.
     */
    public function getSince(int $gameId, int $sinceId): array {
        $st = $this->db->prepare(
            "SELECT r.id, r.emoji, p.name AS player_name
             FROM reactions r
             JOIN players p ON p.id = r.player_id
             WHERE r.game_id = ? AND r.id > ?
             ORDER BY r.id ASC
             LIMIT 30"
        );
        $st->execute([$gameId, $sinceId]);
        return $st->fetchAll();
    }
}
