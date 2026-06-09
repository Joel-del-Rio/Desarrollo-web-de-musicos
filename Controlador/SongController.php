<?php
require_once __DIR__ . '/../Modelo/Database.php';

class SongController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    /** Devuelve todas las canciones ordenadas por género y título */
    public function getSongs(): array {
        $st = $this->db->query(
            "SELECT id, title, artist, year, genre,
                    COALESCE(spotify_url,'') AS spotify_url,
                    COALESCE(youtube_url,'') AS youtube_url
             FROM songs ORDER BY genre, year, title"
        );
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Actualiza los enlaces de streaming de una canción */
    public function updateLinks(): array {
        $id          = (int)($_POST['song_id']      ?? 0);
        $spotifyUrl  = trim($_POST['spotify_url']   ?? '');
        $youtubeUrl  = trim($_POST['youtube_url']   ?? '');

        if (!$id) return ['error' => 'ID de canción inválido'];

        // Validación básica de URLs
        if ($spotifyUrl  && !filter_var($spotifyUrl,  FILTER_VALIDATE_URL)) return ['error' => 'URL de Spotify inválida'];
        if ($youtubeUrl  && !filter_var($youtubeUrl,  FILTER_VALIDATE_URL)) return ['error' => 'URL de YouTube inválida'];

        $this->db->prepare(
            "UPDATE songs SET spotify_url=NULLIF(?,''), youtube_url=NULLIF(?,'') WHERE id=?"
        )->execute([$spotifyUrl, $youtubeUrl, $id]);

        return ['success' => true];
    }
}
