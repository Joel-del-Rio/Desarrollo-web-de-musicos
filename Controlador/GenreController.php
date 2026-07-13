<?php
/**
 * GenreController.php — Gestión del catálogo de géneros musicales
 *
 * Permite listar los géneros, añadir uno nuevo y renombrar uno existente
 * desde el panel superadmin (pestaña Canciones).
 */
require_once __DIR__ . '/../Modelo/Genres.php';

class GenreController {

    /** Lista de géneros con su id (para el panel de gestión) */
    public function list(): array {
        return ['success' => true, 'genres' => Genres::allWithIds()];
    }

    /** Añade un género nuevo al catálogo */
    public function add(): array {
        return Genres::add(trim($_POST['name'] ?? ''));
    }

    /** Renombra un género existente, actualizando en cascada canciones y partidas */
    public function rename(): array {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        return Genres::rename($id, $name);
    }
}
