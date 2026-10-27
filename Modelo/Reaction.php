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

        $this->db->beginTransaction();
        try {
            $st=$this->db->prepare('SELECT * FROM games WHERE id=? FOR UPDATE');$st->execute([$gameId]);$game=$st->fetch();
            if (!$game || !self::allowed($game)) return ['error'=>'Las reacciones están desactivadas mientras se clasifica la canción'];
            $st=$this->db->prepare('SELECT id FROM players WHERE id=? AND game_id=?');$st->execute([$playerId,$gameId]);
            if (!$st->fetchColumn()) return ['error'=>'Jugador no encontrado en esta partida'];
            $st=$this->db->prepare('SELECT id FROM reactions WHERE player_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 5 SECOND LIMIT 1');$st->execute([$playerId]);
            if ($st->fetchColumn()) return ['error'=>'Espera unos segundos antes de reaccionar otra vez'];
            $this->db->prepare('INSERT INTO reactions (game_id,player_id,emoji) VALUES (?,?,?)')->execute([$gameId,$playerId,$emoji]);
            $id=(int)$this->db->lastInsertId();$this->db->commit();return ['success'=>true,'id'=>$id];
        } finally {if($this->db->inTransaction())$this->db->rollBack();}
    }

    public static function allowed(array $game): bool {
        return $game['status']!=='question' || (!empty($game['live_mode']) && !empty($game['live_paused']));
    }
    public static function state(array $game): array {
        $allowed=self::allowed($game);
        return ['reactions_allowed'=>$allowed,'new_reactions'=>$allowed?(new self())->getSince((int)$game['id'],0):[]];
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
             WHERE r.game_id = ? AND r.id > ? AND r.created_at >= UTC_TIMESTAMP()-INTERVAL 8 SECOND
             ORDER BY r.id DESC
             LIMIT 30"
        );
        $st->execute([$gameId, $sinceId]);
        return $st->fetchAll();
    }
}
