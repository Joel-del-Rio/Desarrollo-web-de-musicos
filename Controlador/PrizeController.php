<?php
require_once __DIR__ . '/../Modelo/Database.php';

class PrizeController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->pdo();
    }

    /** Verificar credenciales de admin */
    public function login(): array {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';
        if ($email === 'joel@nite.black' && hash('sha256', $pass) === '4f1cf128cc1cda92976abb1be3455ace44aa9b5b4a3459ca5f89c0657536cc40') {
            return ['success' => true];
        }
        return ['error' => 'Credenciales incorrectas'];
    }

    private const UPLOAD_DIR = __DIR__ . '/../assets/images/premios/';
    private const UPLOAD_URL = 'assets/images/premios/';
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** Premios activos (vista pública) */
    public function getCatalog(): array {
        return $this->db->query(
            "SELECT id, name, description, points_cost, stock, image FROM prizes_catalog WHERE active=1 ORDER BY points_cost ASC"
        )->fetchAll();
    }

    /** Todos los premios (vista admin) */
    public function getAll(): array {
        return $this->db->query(
            "SELECT * FROM prizes_catalog ORDER BY active DESC, points_cost ASC"
        )->fetchAll();
    }

    /** Crear o actualizar premio */
    public function save(): array {
        $id    = (int)($_POST['id']          ?? 0);
        $name  = trim($_POST['name']         ?? '');
        $desc  = trim($_POST['description']  ?? '');
        $cost  = max(1, (int)($_POST['points_cost'] ?? 1000));
        $stock = (int)($_POST['stock']       ?? -1);

        if (!$name) return ['error' => 'El nombre es obligatorio'];

        // Manejar imagen subida
        $imageName = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $file = $_FILES['image'];
            if (!in_array($file['type'], self::ALLOWED_TYPES)) {
                return ['error' => 'Formato no permitido. Usa JPG, PNG, GIF o WebP'];
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                return ['error' => 'La imagen no puede superar 2 MB'];
            }
            $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
            $imageName = uniqid('prize_') . '.' . strtolower($ext);
            if (!move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $imageName)) {
                return ['error' => 'Error al guardar la imagen'];
            }
        }

        if ($id) {
            if ($imageName) {
                // Borrar imagen anterior
                $old = $this->db->prepare("SELECT image FROM prizes_catalog WHERE id=?");
                $old->execute([$id]);
                $prev = $old->fetchColumn();
                if ($prev && file_exists(self::UPLOAD_DIR . $prev)) unlink(self::UPLOAD_DIR . $prev);
                $this->db->prepare(
                    "UPDATE prizes_catalog SET name=?, description=?, points_cost=?, stock=?, image=? WHERE id=?"
                )->execute([$name, $desc ?: null, $cost, $stock, $imageName, $id]);
            } else {
                $this->db->prepare(
                    "UPDATE prizes_catalog SET name=?, description=?, points_cost=?, stock=? WHERE id=?"
                )->execute([$name, $desc ?: null, $cost, $stock, $id]);
            }
        } else {
            $this->db->prepare(
                "INSERT INTO prizes_catalog (name, description, points_cost, stock, image) VALUES (?,?,?,?,?)"
            )->execute([$name, $desc ?: null, $cost, $stock, $imageName]);
            $id = (int)$this->db->lastInsertId();
        }
        return ['success' => true, 'id' => $id];
    }

    /** Activar / desactivar premio */
    public function toggle(): array {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) return ['error' => 'ID requerido'];
        $this->db->prepare("UPDATE prizes_catalog SET active = 1 - active WHERE id=?")->execute([$id]);
        return ['success' => true];
    }

    /** Eliminar premio */
    public function delete(): array {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) return ['error' => 'ID requerido'];
        $this->db->prepare("DELETE FROM prizes_catalog WHERE id=?")->execute([$id]);
        return ['success' => true];
    }

    /** Clasificación global top 50 */
    public function getLeaderboard(): array {
        return $this->db->query(
            "SELECT email, name, total_points FROM global_scores ORDER BY total_points DESC LIMIT 50"
        )->fetchAll();
    }

    /** Puntos de un email concreto */
    public function getMyScore(): array {
        $email = filter_var(trim($_GET['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) return ['error' => 'Email inválido'];
        $st = $this->db->prepare("SELECT * FROM global_scores WHERE email=?");
        $st->execute([$email]);
        $row = $st->fetch();
        if (!$row) return ['total_points' => 0, 'rank' => null];
        $rankSt = $this->db->prepare("SELECT COUNT(*)+1 FROM global_scores WHERE total_points > ?");
        $rankSt->execute([$row['total_points']]);
        $rank = (int)$rankSt->fetchColumn();
        return ['total_points' => (int)$row['total_points'], 'name' => $row['name'], 'rank' => $rank];
    }
}
