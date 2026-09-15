<?php
/**
 * Auth.php — Sesión de administrador (superadmin y panel de premios)
 *
 * El login abre una sesión PHP en el servidor. Las acciones de escritura de la
 * API comprueban esa sesión antes de ejecutarse (ver PROTECTED_ACTIONS en api.php),
 * así que ocultar o mostrar el panel en el navegador ya no basta para usarlas.
 */
class Auth {
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
        $ok = hash_equals(ADMIN_EMAIL, strtolower(trim($email)))
           && ADMIN_PASS_HASH !== ''
           && password_verify($password, ADMIN_PASS_HASH);
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
