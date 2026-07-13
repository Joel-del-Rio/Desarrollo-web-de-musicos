<?php
/**
 * MemeController.php — Catálogo de memes (modo de juego "memes")
 *
 * Permite subir imágenes de memes con su año y género, listarlas
 * y eliminarlas desde el panel superadmin. Funciona en paralelo
 * al catálogo de canciones (Option A: tablas separadas).
 */
require_once __DIR__ . '/../Modelo/Database.php';
require_once __DIR__ . '/../Modelo/Genres.php';

class MemeController {
    private PDO $db;

    private const UPLOAD_DIR    = __DIR__ . '/../assets/images/memes/';
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    /** Lista todos los memes ordenados por género y año */
    public function getMemes(): array {
        return $this->db->query(
            "SELECT id, image_url, title, year, genre FROM memes ORDER BY genre, year, id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Sube una imagen nueva y la añade al catálogo de memes */
    public function addMeme(): array {
        $title = trim($_POST['title'] ?? '');
        $year  = (int)($_POST['year']  ?? 0);
        $genre = trim($_POST['genre'] ?? '');

        if ($year < 1900 || $year > 2100) return ['error' => 'Año inválido'];
        if (!in_array($genre, Genres::all(), true)) return ['error' => 'Género inválido'];
        if (empty($_FILES['image']['tmp_name'])) return ['error' => 'Debes seleccionar una imagen'];

        $file = $_FILES['image'];
        if (!in_array($file['type'], self::ALLOWED_TYPES, true)) {
            return ['error' => 'Formato no permitido. Usa JPG, PNG, GIF o WebP'];
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            return ['error' => 'La imagen no puede superar 3 MB'];
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('meme_') . '.' . strtolower($ext);
        if (!move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $filename)) {
            return ['error' => 'Error al guardar la imagen'];
        }

        $this->db->prepare(
            "INSERT INTO memes (image_url, title, year, genre) VALUES (?,?,?,?)"
        )->execute([$filename, $title ?: null, $year, $genre]);

        return ['success' => true, 'id' => (int)$this->db->lastInsertId(), 'image_url' => $filename];
    }

    /** Elimina un meme del catálogo y borra su imagen del disco */
    public function deleteMeme(): array {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) return ['error' => 'ID de meme inválido'];

        $st = $this->db->prepare("SELECT image_url FROM memes WHERE id=?");
        $st->execute([$id]);
        $imageUrl = $st->fetchColumn();
        if ($imageUrl === false) return ['error' => 'Meme no encontrado'];

        try {
            $this->db->prepare("DELETE FROM memes WHERE id=?")->execute([$id]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['error' => 'No se puede eliminar: este meme ya se ha usado en alguna partida'];
            }
            throw $e;
        }

        if ($imageUrl && file_exists(self::UPLOAD_DIR . $imageUrl)) {
            unlink(self::UPLOAD_DIR . $imageUrl);
        }

        return ['success' => true];
    }
}
