<?php
/**
 * MemeController.php — Catálogo de memes (modo de juego "memes")
 *
 * Permite subir imágenes de memes con su año, listarlas y eliminarlas
 * desde el panel superadmin. Funciona en paralelo al catálogo de
 * canciones (Option A: tablas separadas). Los memes no tienen género.
 */
require_once __DIR__ . '/../Modelo/Database.php';

class MemeController {
    private PDO $db;

    private const UPLOAD_DIR    = __DIR__ . '/../assets/images/memes/';
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    /** Lista todos los memes ordenados por año */
    public function getMemes(): array {
        return $this->db->query(
            "SELECT id, image_url, title, year FROM memes ORDER BY year, id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Sube una imagen nueva (por archivo o por URL) y la añade al catálogo de memes */
    public function addMeme(): array {
        $title    = trim($_POST['title'] ?? '');
        $year     = (int)($_POST['year']  ?? 0);
        $imageUrl = trim($_POST['image_url'] ?? '');
        $hasFile  = !empty($_FILES['image']['tmp_name']);

        if ($year < 1900 || $year > 2100) return ['error' => 'Año inválido'];
        if ($hasFile && $imageUrl)  return ['error' => 'Indica solo una imagen: archivo o URL, no ambos'];
        if (!$hasFile && !$imageUrl) return ['error' => 'Debes subir una imagen o indicar una URL'];

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
        return $filename;
    }

    /** Descarga una imagen desde una URL externa y la guarda en el servidor */
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
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_RANGE          => '0-3145728', // tope de 3 MB
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HitstoricBot/1.0)',
        ]);
        $data = curl_exec($ch);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $ok   = $data !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400;
        curl_close($ch);

        if (!$ok || !$data) return ['error' => 'No se pudo descargar la imagen de esa URL'];
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return ['error' => 'La URL no apunta a una imagen JPG, PNG, GIF o WebP'];
        }
        if (strlen($data) > 3 * 1024 * 1024) {
            return ['error' => 'La imagen no puede superar 3 MB'];
        }

        $ext      = strtolower(explode('/', $type)[1]);
        $filename = uniqid('meme_') . '.' . $ext;
        if (file_put_contents(self::UPLOAD_DIR . $filename, $data) === false) {
            return ['error' => 'Error al guardar la imagen'];
        }
        return $filename;
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
