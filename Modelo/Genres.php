<?php
/**
 * Genres.php — Catálogo de géneros musicales (tabla `genres`)
 *
 * Antes vivían como una constante fija en config.php; ahora el superadmin
 * puede añadir géneros nuevos o renombrar los existentes desde el panel.
 */
require_once __DIR__ . '/Database.php';

class Genres {
    private static function db(): PDO {
        return Database::getInstance()->pdo();
    }

    /** Géneros personalizados (sin "Todos"), ordenados alfabéticamente */
    public static function all(): array {
        return self::db()->query("SELECT name FROM genres ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Géneros con "Todos" al principio (para selectores de partida) */
    public static function allWithTodos(): array {
        return array_merge(['Todos'], self::all());
    }

    /** Lista con id + nombre, para el panel de gestión */
    public static function allWithIds(): array {
        return self::db()->query("SELECT id, name FROM genres ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Añade un género nuevo. Devuelve error si está vacío o ya existe (sin distinguir mayúsculas) */
    public static function add(string $name): array {
        $name = trim($name);
        if (!$name) return ['error' => 'El nombre del género no puede estar vacío'];
        if (strcasecmp($name, 'Todos') === 0) return ['error' => 'Ese nombre está reservado'];

        $dup = self::db()->prepare("SELECT id FROM genres WHERE LOWER(name)=LOWER(?)");
        $dup->execute([$name]);
        if ($dup->fetchColumn()) return ['error' => 'Ese género ya existe'];

        self::db()->prepare("INSERT INTO genres (name) VALUES (?)")->execute([$name]);
        return ['success' => true, 'id' => (int)self::db()->lastInsertId()];
    }

    /**
     * Renombra un género existente y actualiza en cascada las canciones
     * y partidas que ya lo tenían asignado, para no dejar datos huérfanos.
     */
    public static function rename(int $id, string $newName): array {
        $newName = trim($newName);
        if (!$id) return ['error' => 'ID de género inválido'];
        if (!$newName) return ['error' => 'El nombre del género no puede estar vacío'];
        if (strcasecmp($newName, 'Todos') === 0) return ['error' => 'Ese nombre está reservado'];

        $db = self::db();

        $cur = $db->prepare("SELECT name FROM genres WHERE id=?");
        $cur->execute([$id]);
        $oldName = $cur->fetchColumn();
        if ($oldName === false) return ['error' => 'Género no encontrado'];
        if ($oldName === $newName) return ['success' => true];

        $dup = $db->prepare("SELECT id FROM genres WHERE LOWER(name)=LOWER(?) AND id<>?");
        $dup->execute([$newName, $id]);
        if ($dup->fetchColumn()) return ['error' => 'Ya existe otro género con ese nombre'];

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE genres SET name=? WHERE id=?")->execute([$newName, $id]);
            $db->prepare("UPDATE songs SET genre=? WHERE genre=?")->execute([$newName, $oldName]);
            $db->prepare("UPDATE games SET selected_genre=? WHERE selected_genre=?")->execute([$newName, $oldName]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return ['success' => true];
    }
}
