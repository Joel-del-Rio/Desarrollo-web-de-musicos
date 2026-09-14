<?php
/**
 * Auth.php — Sesión de administrador (superadmin y panel de premios)
 *
 * El login abre una sesión PHP en el servidor. Las acciones de escritura de la
 * API comprueban esa sesión antes de ejecutarse (ver PROTECTED_ACTIONS en api.php),
 * así que ocultar o mostrar el panel en el navegador ya no basta para usarlas.
 */
class Auth {
    private const ADMIN_EMAIL     = 'joel@nite.black';
    private const ADMIN_PASS_HASH = '4f1cf128cc1cda92976abb1be3455ace44aa9b5b4a3459ca5f89c0657536cc40';

    /** Arranca la sesión con cookie HttpOnly/SameSite (idempotente) */
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('HITSTORIC_SID');
        session_start();
    }

    /** Valida credenciales y, si son correctas, marca la sesión como administrador */
    public static function login(string $email, string $password): bool {
        $ok = hash_equals(self::ADMIN_EMAIL, strtolower(trim($email)))
           && hash_equals(self::ADMIN_PASS_HASH, hash('sha256', $password));
        if (!$ok) return false;

        self::start();
        session_regenerate_id(true); // evita fijación de sesión
        $_SESSION['is_admin'] = true;
        return true;
    }

    public static function check(): bool {
        self::start();
        return !empty($_SESSION['is_admin']);
    }

    public static function logout(): void {
        self::start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
        session_destroy();
    }
}
