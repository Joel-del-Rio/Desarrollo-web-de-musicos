<?php
/**
 * Player.php — Modelo de jugador
 *
 * Gestiona la creación de jugadores, su línea del tiempo personal,
 * el envío y evaluación de respuestas, y la acumulación de puntos
 * tanto en la partida como en el ranking global.
 */
require_once __DIR__ . '/Database.php';

class Player {
    private PDO $db;

    // Paleta de colores para los avatares (asignación aleatoria al crear jugador)
    private const COLORS = ['#e94560','#4ECDC4','#45B7D1','#FF6B35','#DDA0DD','#2ecc71','#f39c12'];

    // Emojis de avatar seleccionables — misma lista usada en el frontend para validar
    public const AVATARS = [
        '🙂','😎','🤠','🥳','👽','🤖','🐱','🐶','🦊','🐼',
        '🐸','🐵','🦁','🐯','🐧','🦄','🐙','🐢','🦉','🐝',
    ];

    // Complementos de personalización — vacío ('') siempre válido como "Ninguno"
    public const HAIR = ['🦱','🦰','🦳','🦲','💇','🎀'];
    public const GLASSES = ['👓','🕶️'];
    public const HATS = ['🧢','🎩','👒','🎓'];
    public const HEADPHONES = ['🎧'];

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    // ── CRUD básico ───────────────────────────────────

    /**
     * Crea un nuevo jugador en la partida con un color de avatar aleatorio.
     * @return array  id, name, color, avatar y complementos del jugador creado
     */
    public function create(
        int $gameId, string $name, string $email = '', string $avatar = '',
        string $hair = '', string $glasses = '', string $hat = '', string $headphones = ''
    ): array {
        $color = self::COLORS[random_int(0, count(self::COLORS) - 1)];
        if (!in_array($avatar, self::AVATARS, true)) {
            $avatar = self::AVATARS[random_int(0, count(self::AVATARS) - 1)];
        }
        $hair       = in_array($hair, self::HAIR, true) ? $hair : '';
        $glasses    = in_array($glasses, self::GLASSES, true) ? $glasses : '';
        $hat        = in_array($hat, self::HATS, true) ? $hat : '';
        $headphones = in_array($headphones, self::HEADPHONES, true) ? $headphones : '';

        $this->db->prepare(
            "INSERT INTO players (game_id, name, avatar_color, avatar, hair, glasses, hat, headphones, email)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([$gameId, $name, $color, $avatar, $hair, $glasses, $hat, $headphones, $email ?: null]);
        return [
            'id' => (int)$this->db->lastInsertId(), 'name' => $name, 'color' => $color, 'avatar' => $avatar,
            'hair' => $hair, 'glasses' => $glasses, 'hat' => $hat, 'headphones' => $headphones,
        ];
    }

    /** Devuelve todos los datos de un jugador por su ID */
    public function getById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM players WHERE id=?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Devuelve todos los jugadores de una partida, ordenados por puntuación */
    public function getByGame(int $gameId): array {
        $st = $this->db->prepare(
            "SELECT id, name, score, avatar_color, avatar, hair, glasses, hat, headphones, streak
             FROM players
             WHERE game_id=?
             ORDER BY score DESC, name ASC"
        );
        $st->execute([$gameId]);
        return $st->fetchAll();
    }

    /** Elimina a un jugador de la partida (usado por el dinamizador para expulsar) */
    public function remove(int $playerId, int $gameId): bool {
        $st = $this->db->prepare("DELETE FROM players WHERE id=? AND game_id=?");
        $st->execute([$playerId, $gameId]);
        return $st->rowCount() > 0;
    }

    /** Cambia el avatar del jugador (solo permitido mientras la partida está en espera) */
    public function updateAvatar(int $playerId, string $avatar): bool {
        if (!in_array($avatar, self::AVATARS, true)) return false;
        $st = $this->db->prepare("UPDATE players SET avatar=? WHERE id=?");
        $st->execute([$avatar, $playerId]);
        return true;
    }

    /** Cambia los complementos de personalización (pelo, gafas, sombrero, auriculares) */
    public function updateCustomization(int $playerId, string $hair, string $glasses, string $hat, string $headphones): bool {
        $hair       = in_array($hair, self::HAIR, true) ? $hair : '';
        $glasses    = in_array($glasses, self::GLASSES, true) ? $glasses : '';
        $hat        = in_array($hat, self::HATS, true) ? $hat : '';
        $headphones = in_array($headphones, self::HEADPHONES, true) ? $headphones : '';
        $st = $this->db->prepare("UPDATE players SET hair=?, glasses=?, hat=?, headphones=? WHERE id=?");
        $st->execute([$hair, $glasses, $hat, $headphones, $playerId]);
        return true;
    }

    /** Actualiza el timestamp de última actividad del jugador (keep-alive) */
    public function ping(int $playerId): void {
        $this->db->prepare("UPDATE players SET last_seen=NOW() WHERE id=?")->execute([$playerId]);
    }

    // ── Timeline ──────────────────────────────────────

    /**
     * Devuelve las canciones ya colocadas en la línea del tiempo del jugador,
     * ordenadas cronológicamente por año para mostrarlas en la UI.
     */
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

    // ── Respuestas ────────────────────────────────────

    /** Comprueba si el jugador ya respondió la canción de esta ronda */
    public function hasAnswered(int $playerId, int $gameId, int $songId): bool {
        $st = $this->db->prepare(
            "SELECT 1 FROM answers WHERE player_id=? AND game_id=? AND song_id=?"
        );
        $st->execute([$playerId, $gameId, $songId]);
        return (bool)$st->fetch();
    }

    /**
     * Procesa la respuesta de posición del jugador:
     * 1. Evalúa si la posición elegida es cronológicamente correcta.
     * 2. Gestiona la racha: acierto → +1, fallo → reset a 0.
     * 3. Calcula multiplicador de racha (×1.0 hasta racha<3; ×1.3 en racha=3, …, ×2.0 en racha≥10).
     * 4. Calcula los puntos base (500 + bonus velocidad) y los multiplica por el multiplicador.
     * 5. Guarda la respuesta en BD.
     * 6. Si es correcta: añade la canción al timeline y suma puntos.
     * 7. En partidas de PIN individual: acumula puntos en el ranking global.
     *
     * @param int $position  Índice en el timeline donde el jugador coloca la canción
     *                       (0 = antes de todo, N = después de todo)
     * @return array  correct, points, streak, multiplier
     */
    public function submitPositionAnswer(
        int $playerId, int $gameId, int $songId,
        int $position, int $songYear,
        int $timeLeft, int $questionTime
    ): array {
        $timeline  = $this->getTimeline($playerId, $gameId);
        $years     = array_column($timeline, 'year');
        $isCorrect = $this->isPositionCorrect($years, $position, $songYear);

        // Leer racha actual del jugador
        $stStreak = $this->db->prepare("SELECT streak FROM players WHERE id=?");
        $stStreak->execute([$playerId]);
        $currentStreak = (int)($stStreak->fetchColumn() ?: 0);

        // Actualizar racha: acierto incrementa, fallo la resetea a 0
        $newStreak  = $isCorrect ? $currentStreak + 1 : 0;
        // Multiplicador: ×1.0 si racha<3; ×(1.0 + racha×0.1) si racha≥3, máx ×2.0
        $multiplier = $newStreak >= 3 ? min(2.0, 1.0 + $newStreak * 0.1) : 1.0;

        // Puntos base: 500 + hasta 500 bonus por velocidad; aplicar multiplicador si acierto
        $points = 0;
        if ($isCorrect) {
            $base   = 500 + (int)round(500 * ($timeLeft / max(1, $questionTime)));
            $points = (int)round($base * $multiplier);
        }

        // Guardar respuesta (INSERT IGNORE evita duplicados si el jugador reenvía)
        $st = $this->db->prepare(
            "INSERT IGNORE INTO answers
             (game_id, player_id, song_id, position_guess, is_correct, points_earned)
             VALUES (?,?,?,?,?,?)"
        );
        $st->execute([$gameId, $playerId, $songId, $position, $isCorrect ? 1 : 0, $points]);

        if ($this->db->lastInsertId() > 0) {
            // Actualizar racha en BD siempre que la respuesta sea nueva (no duplicado)
            $this->db->prepare("UPDATE players SET streak=? WHERE id=?")->execute([$newStreak, $playerId]);

            if ($isCorrect) {
                // Añadir la canción acertada a la línea del tiempo del jugador
                $this->db->prepare(
                    "INSERT IGNORE INTO player_timeline (player_id, game_id, song_id) VALUES (?,?,?)"
                )->execute([$playerId, $gameId, $songId]);

                // Sumar puntos a la puntuación de la partida
                $this->db->prepare("UPDATE players SET score=score+? WHERE id=?")->execute([$points, $playerId]);

                // Acumular en ranking global solo si la partida es de PIN individual
                $gmSt = $this->db->prepare("SELECT pin_mode FROM games WHERE id=?");
                $gmSt->execute([$gameId]);
                $gameRow = $gmSt->fetch();

                if (($gameRow['pin_mode'] ?? '') === 'individual') {
                    $pl = $this->getById($playerId);
                    if (!empty($pl['email'])) {
                        // UPSERT: si ya existe el email, suma los puntos; si no, lo crea
                        $this->db->prepare(
                            "INSERT INTO global_scores (email, name, total_points)
                             VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE total_points = total_points + ?, name = VALUES(name)"
                        )->execute([$pl['email'], $pl['name'], $points, $points]);
                    }
                }
            }
        }

        return [
            'correct'    => $isCorrect,
            'points'     => $points,
            'streak'     => $newStreak,
            'multiplier' => $multiplier,
        ];
    }

    /** Cuenta cuántos jugadores han respondido ya la canción actual */
    public function getAnswerCount(int $gameId, int $songId): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM answers WHERE game_id=? AND song_id=?");
        $st->execute([$gameId, $songId]);
        return (int)$st->fetchColumn();
    }

    /** Devuelve los resultados de todos los jugadores para la ronda actual */
    public function getRoundResults(int $gameId, int $songId): array {
        $st = $this->db->prepare(
            "SELECT p.name, p.avatar_color, p.avatar, p.hair, p.glasses, p.hat, p.headphones, a.position_guess, a.is_correct, a.points_earned
             FROM answers a
             JOIN players p ON a.player_id = p.id
             WHERE a.game_id=? AND a.song_id=?
             ORDER BY a.points_earned DESC, a.answered_at ASC"
        );
        $st->execute([$gameId, $songId]);
        return $st->fetchAll();
    }

    // ── Lógica de evaluación ──────────────────────────

    /**
     * Determina si la posición elegida es cronológicamente válida.
     * La canción nueva debe quedar entre la canción anterior y la siguiente
     * del timeline ya ordenado. Permite empates de año (>=, <=).
     *
     * Ejemplos con timeline [1980, 1995, 2010]:
     *   position=0, newYear=1975 → antes de 1980 → correcto
     *   position=1, newYear=1985 → entre 1980 y 1995 → correcto
     *   position=2, newYear=2020 → no encaja entre 1995 y 2010 → incorrecto
     */
    private function isPositionCorrect(array $years, int $position, int $newYear): bool {
        sort($years);
        $n    = count($years);
        $prev = $position > 0 ? $years[$position - 1] : 0;
        $next = $position < $n ? $years[$position]    : PHP_INT_MAX;
        return $newYear >= $prev && $newYear <= $next;
    }
}
