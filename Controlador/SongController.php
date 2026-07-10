<?php
/**
 * SongController.php — Controlador del catálogo de canciones
 *
 * Permite listar todas las canciones y actualizar sus URLs
 * de streaming (Spotify/YouTube) desde el panel de gestión.
 */
require_once __DIR__ . '/../Modelo/Database.php';

class SongController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    /** Devuelve todas las canciones ordenadas por género, año y título */
    public function getSongs(): array {
        $st = $this->db->query(
            "SELECT id, title, artist, year, genre,
                    COALESCE(spotify_url,'') AS spotify_url,
                    COALESCE(youtube_url,'') AS youtube_url
             FROM songs ORDER BY genre, year, title"
        );
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza los enlaces de Spotify y/o YouTube de una canción.
     * Si se pasa una cadena vacía se guarda NULL (NULLIF).
     */
    public function updateLinks(): array {
        $id         = (int)($_POST['song_id']    ?? 0);
        $spotifyUrl = trim($_POST['spotify_url'] ?? '');
        $youtubeUrl = trim($_POST['youtube_url'] ?? '');

        if (!$id) return ['error' => 'ID de canción inválido'];

        // Validación básica antes de persistir
        if ($spotifyUrl && !filter_var($spotifyUrl, FILTER_VALIDATE_URL)) return ['error' => 'URL de Spotify inválida'];
        if ($youtubeUrl && !filter_var($youtubeUrl, FILTER_VALIDATE_URL)) return ['error' => 'URL de YouTube inválida'];

        $this->db->prepare(
            "UPDATE songs SET spotify_url=NULLIF(?,''), youtube_url=NULLIF(?,'') WHERE id=?"
        )->execute([$spotifyUrl, $youtubeUrl, $id]);

        return ['success' => true];
    }

    /**
     * Añade una canción nueva al catálogo (desde el buscador del panel superadmin).
     * Queda disponible de inmediato para cualquier partida que se cree a partir de ahora.
     */
    public function addSong(): array {
        $title  = trim($_POST['title']  ?? '');
        $artist = trim($_POST['artist'] ?? '');
        $year   = (int)($_POST['year']  ?? 0);
        $genre  = trim($_POST['genre']  ?? '');

        if (!$title || !$artist) return ['error' => 'Título y artista son obligatorios'];
        if ($year < 1900 || $year > 2100) return ['error' => 'Año inválido'];
        if (!in_array($genre, GENRES, true) || $genre === 'Todos') return ['error' => 'Género inválido'];

        $dup = $this->db->prepare("SELECT id FROM songs WHERE title=? AND artist=?");
        $dup->execute([$title, $artist]);
        if ($dup->fetchColumn()) return ['error' => 'Esa canción ya está en el catálogo'];

        $this->db->prepare(
            "INSERT INTO songs (title, artist, year, genre) VALUES (?,?,?,?)"
        )->execute([$title, $artist, $year, $genre]);

        return ['success' => true, 'id' => (int)$this->db->lastInsertId()];
    }
}
