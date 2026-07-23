<?php
/**
 * MemeController.php — Catálogo de memes (modo de juego "memes")
 *
 * Permite subir vídeos cortos de memes con su año, listarlos y eliminarlos
 * desde el panel superadmin. Funciona en paralelo al catálogo de
 * canciones (Option A: tablas separadas). Los memes no tienen género.
 * La columna `image_url` se mantiene por compatibilidad con la BD, pero
 * ahora guarda el nombre de archivo del vídeo.
 */
require_once __DIR__ . '/../Modelo/Database.php';

class MemeController {
    private PDO $db;

    private const UPLOAD_DIR    = __DIR__ . '/../assets/videos/memes/';
    private const ALLOWED_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];
    private const EXT_BY_TYPE   = [
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
    ];
    private const MAX_SIZE = 15 * 1024 * 1024; // 15 MB

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    /** Lista todos los memes ordenados por año */
    public function getMemes(): array {
        return $this->db->query(
            "SELECT id, image_url, title, year FROM memes ORDER BY year, id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Sube un vídeo nuevo (por archivo o por URL) y lo añade al catálogo de memes */
    public function addMeme(): array {
        $title    = trim($_POST['title'] ?? '');
        $year     = (int)($_POST['year']  ?? 0);
        $imageUrl = trim($_POST['image_url'] ?? '');
        $hasFile  = !empty($_FILES['image']['tmp_name']);

        if ($year < 1900 || $year > 2100) return ['error' => 'Año inválido'];
        if ($hasFile && $imageUrl)  return ['error' => 'Indica solo un vídeo: archivo o URL, no ambos'];
        if (!$hasFile && !$imageUrl) return ['error' => 'Debes subir un vídeo o indicar una URL'];

        $filename = $hasFile ? $this->saveFromUpload($_FILES['image']) : $this->saveFromUrl($imageUrl);
        if (isset($filename['error'])) return $filename;

        $this->db->prepare(
            "INSERT INTO memes (image_url, title, year) VALUES (?,?,?)"
        )->execute([$filename, $title ?: null, $year]);

        return ['success' => true, 'id' => (int)$this->db->lastInsertId(), 'image_url' => $filename];
    }

    /** Guarda un archivo subido por formulario y devuelve su nombre en disco */
    private function saveFromUpload(array $file) {
        if (!in_array($file['type'], self::ALLOWED_TYPES, true)) {
            return ['error' => 'Formato no permitido. Usa MP4, WebM o MOV'];
        }
        if ($file['size'] > self::MAX_SIZE) {
            return ['error' => 'El vídeo no puede superar 15 MB'];
        }

        $ext      = self::EXT_BY_TYPE[$file['type']];
        $filename = uniqid('meme_') . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $filename)) {
            return ['error' => 'Error al guardar el vídeo'];
        }
        return $filename;
    }

    /** Descarga un vídeo desde una URL externa y lo guarda en el servidor */
    private function saveFromUrl(string $url) {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return ['error' => 'URL no válida'];
        }
        // Evitar SSRF: bloquear IPs privadas/locales resueltas del host
        $ip = gethostbyname($parts['host']);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['error' => 'URL no permitida'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_RANGE          => '0-' . self::MAX_SIZE,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HitstoricBot/1.0)',
        ]);
        $data = curl_exec($ch);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $ok   = $data !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400;
        curl_close($ch);

        if (!$ok || !$data) return ['error' => 'No se pudo descargar el vídeo de esa URL'];
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return ['error' => 'La URL no apunta a un vídeo MP4, WebM o MOV'];
        }
        if (strlen($data) > self::MAX_SIZE) {
            return ['error' => 'El vídeo no puede superar 15 MB'];
        }

        $ext      = self::EXT_BY_TYPE[$type];
        $filename = uniqid('meme_') . '.' . $ext;
        if (file_put_contents(self::UPLOAD_DIR . $filename, $data) === false) {
            return ['error' => 'Error al guardar el vídeo'];
        }
        return $filename;
    }

    /** Elimina un meme del catálogo y borra su vídeo del disco */
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
