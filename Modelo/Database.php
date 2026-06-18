<?php
/**
 * Database.php — Singleton de conexión a MySQL via PDO
 *
 * Garantiza una única instancia de PDO por petición HTTP.
 * Al conectar fija la zona horaria de la sesión MySQL a UTC para que
 * los campos TIMESTAMP se lean siempre igual independientemente
 * de la configuración del servidor (soluciona el bug de SiteGround).
 * También lanza las migraciones de esquema si hay versiones pendientes.
 */
class Database {
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct() {
        require_once dirname(__DIR__) . '/config.php';
        require_once __DIR__ . '/Installer.php';

        $dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // lanza excepciones en errores SQL
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // arrays asociativos por defecto
            PDO::ATTR_EMULATE_PREPARES   => false,                    // prepared statements reales
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            // Si la BD no existe todavía, Installer la crea y volvemos a conectar
            if (str_contains($e->getMessage(), 'Unknown database') || $e->getCode() == 1049) {
                Installer::run(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
                $this->pdo->exec("SET time_zone = '+00:00'");
                return;
            }
            throw $e;
        }

        // Forzar UTC en la sesión MySQL para lectura coherente de TIMESTAMP
        $this->pdo->exec("SET time_zone = '+00:00'");

        // Ejecutar migraciones pendientes en cada arranque (es idempotente)
        Installer::run(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }

    /** Devuelve la instancia única (patrón Singleton) */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Devuelve el objeto PDO para ejecutar queries */
    public function pdo(): PDO {
        return $this->pdo;
    }
}
