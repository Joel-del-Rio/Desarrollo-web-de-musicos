<?php
/**
 * MemeController.php — Catálogo de memes (modo de juego "memes")
 *
 * Los memes son vídeos cortos de YouTube que se embeben directamente
 * (no se descargan ni se alojan en el servidor). Se guarda el ID del
 * vídeo y, opcionalmente, el segundo en el que empieza el clip.
 */
require_once __DIR__ . '/../Modelo/Database.php';

class MemeController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    /** Lista todos los memes ordenados por año */
    public function getMemes(): array {
        return $this->db->query(
            "SELECT id, youtube_id, start_seconds, title, year FROM memes ORDER BY year, id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Añade un meme nuevo al catálogo a partir de una URL de YouTube */
    public function addMeme(): array {
        $title    = trim($_POST['title'] ?? '');
        $year     = (int)($_POST['year']  ?? 0);
        $url      = trim($_POST['youtube_url'] ?? '');
        $start    = max(0, (int)($_POST['start_seconds'] ?? 0));

        if ($year < 1900 || $year > 2100) return ['error' => 'Año inválido'];
        if (!$url) return ['error' => 'Indica la URL del vídeo de YouTube'];

        $videoId = self::extractYoutubeId($url);
        if (!$videoId) return ['error' => 'No se ha reconocido un enlace de YouTube válido'];

        $this->db->prepare(
            "INSERT INTO memes (youtube_id, start_seconds, title, year) VALUES (?,?,?,?)"
        )->execute([$videoId, $start, $title ?: null, $year]);

        return ['success' => true, 'id' => (int)$this->db->lastInsertId(), 'youtube_id' => $videoId];
    }

    /** Edita un meme existente (útil para corregir la URL/año sin perder su historial de partidas) */
    public function updateMeme(): array {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $year  = (int)($_POST['year']  ?? 0);
        $url   = trim($_POST['youtube_url'] ?? '');
        $start = max(0, (int)($_POST['start_seconds'] ?? 0));

        if (!$id) return ['error' => 'ID de meme inválido'];
        if ($year < 1900 || $year > 2100) return ['error' => 'Año inválido'];
        if (!$url) return ['error' => 'Indica la URL del vídeo de YouTube'];

        $videoId = self::extractYoutubeId($url);
        if (!$videoId) return ['error' => 'No se ha reconocido un enlace de YouTube válido'];

        $st = $this->db->prepare(
            "UPDATE memes SET youtube_id=?, start_seconds=?, title=?, year=? WHERE id=?"
        );
        $st->execute([$videoId, $start, $title ?: null, $year, $id]);
        if ($st->rowCount() === 0) return ['error' => 'Meme no encontrado'];

        return ['success' => true, 'id' => $id, 'youtube_id' => $videoId];
    }

    /** Extrae el ID de vídeo de las URLs habituales de YouTube (watch, youtu.be, shorts, embed) */
    private static function extractYoutubeId(string $url): ?string {
        $patterns = [
            '~youtube\.com/watch\?v=([A-Za-z0-9_-]{11})~',
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/embed/([A-Za-z0-9_-]{11})~',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) return $m[1];
        }
        // Por si pegan directamente el ID de 11 caracteres
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) return $url;
        return null;
    }

    /** Elimina un meme del catálogo */
    public function deleteMeme(): array {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) return ['error' => 'ID de meme inválido'];

        try {
            $st = $this->db->prepare("DELETE FROM memes WHERE id=?");
            $st->execute([$id]);
            if ($st->rowCount() === 0) return ['error' => 'Meme no encontrado'];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['error' => 'No se puede eliminar: este meme ya se ha usado en alguna partida'];
            }
            throw $e;
        }

        return ['success' => true];
    }
}
